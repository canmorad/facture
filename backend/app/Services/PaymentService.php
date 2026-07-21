<?php

namespace App\Services;

use App\Contracts\PayableInterface;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\Invoice;
use App\Models\Deposit;
use App\Models\BalanceInvoice;
use App\Models\Document;
use App\Models\CashTransaction;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\PaymentDocument;
use App\Models\DocumentRelationship;
use App\Models\DocumentWorkflowHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        protected NumberingService $numberingService,
        protected DocumentService $documentService,
    ) {}

    /**
     * Process a payment for any payable document
     *
     * @param int $documentId The document ID (from documents table)
     * @param array $paymentData Payment data
     * @return Payment
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function processPayment(int $documentId, array $paymentData): Payment
    {
        $companyId = $this->getCompanyId();

        try {
            // Find the document first
            $document = Document::where('company_id', $companyId)
                ->where('id', $documentId)
                ->firstOrFail();

            // Get the payable model
            $payable = $document->documentable;

            // Validate it implements PayableInterface
            if (!($payable instanceof PayableInterface)) {
                Log::warning('Payment attempted on non-payable document', [
                    'document_id' => $documentId,
                    'document_type' => get_class($payable),
                ]);
                throw new \InvalidArgumentException('Ce type de document ne peut pas recevoir de paiements.');
            }

            // Validate payment eligibility
            $this->validatePaymentEligibility($payable);
            $this->validatePaymentAmount($payable, $paymentData['amount'] ?? 0);

            DB::beginTransaction();

            try {
                // Create the payment record
                $payment = $this->createPaymentRecord($companyId, $document, $payable, $paymentData);

                // Process based on payment mode
                switch ($paymentData['payment_mode']) {
                    case 'espece':
                        $this->processCashPayment($payment, $paymentData);
                        break;
                    case 'cheque':
                    case 'lcn':
                        $this->processDocumentaryPayment($payment, $paymentData);
                        break;
                    case 'virement':
                    case 'carte':
                        $this->processDirectPayment($payment, $paymentData);
                        break;
                    default:
                        throw new \InvalidArgumentException('Mode de paiement non reconnu.');
                }

                // Create document relationship for allocation tracking
                $this->createDocumentRelationship($document, $payment);

                // Mark payment as completed
                $payment->markAsCompleted();

                // Update document status if fully paid
                if ($this->shouldAutoMarkPaid($companyId) && $payable->isFullyPaid()) {
                    $payable->markAsPaid();
                }

                // Record workflow history
                $this->recordPaymentHistory($document, $payment);

                DB::commit();

                Log::info('Payment processed successfully', [
                    'payment_id' => $payment->id,
                    'document_id' => $documentId,
                    'amount' => $payment->amount,
                    'payment_mode' => $payment->payment_mode,
                ]);

                return $payment->load(['cashTransaction', 'paymentDocument', 'payable.document']);
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Payment processing transaction failed', [
                    'document_id' => $documentId,
                    'payment_data' => $this->sanitizePaymentData($paymentData),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Payment attempted on non-existent document', [
                'document_id' => $documentId,
                'company_id' => $companyId,
            ]);
            throw new \InvalidArgumentException('Document introuvable.');
        } catch (\InvalidArgumentException $e) {
            // Re-throw validation exceptions as-is
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Unexpected error during payment processing', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Une erreur est survenue lors du traitement du paiement. Veuillez réessayer.');
        }
    }

    /**
     * Create payment record with proper handling of invoice_id
     */
    protected function createPaymentRecord(int $companyId, Document $document, PayableInterface $payable, array $paymentData): Payment
    {
        // Determine invoice_id for backward compatibility
        $invoiceId = null;
        if ($payable instanceof Invoice) {
            $invoiceId = $payable->id;
        }

        $paymentData = [
            'company_id' => $companyId,
            'invoice_id' => $invoiceId,
            'payable_type' => $payable->getPayableType(),
            'payable_id' => $payable->id,
            'customer_id' => $document->customer_id,
            'payment_mode' => $paymentData['payment_mode'],
            'amount' => $paymentData['amount'],
            'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
            'reference' => $paymentData['reference'] ?? null,
            'notes' => $paymentData['notes'] ?? null,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ];

        try {
            $payment = Payment::create($paymentData);
            Log::debug('Payment record created', ['payment_id' => $payment->id]);
            return $payment;
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database constraint violations
            if (str_contains($e->getMessage(), 'invoice_id')) {
                Log::error('Database constraint error on invoice_id', [
                    'error' => $e->getMessage(),
                    'payable_type' => $payable->getPayableType(),
                    'payable_id' => $payable->id,
                ]);
                throw new \RuntimeException(
                    'Erreur de base de données : La relation polymorphique n\'est pas correctement configurée. ' .
                    'Veuillez exécuter la migration : php artisan migrate'
                );
            }
            throw $e;
        }
    }

    /**
     * Process cash payment - creates CashTransaction
     */
    protected function processCashPayment(Payment $payment, array $data): void
    {
        try {
            $companyId = $payment->company_id;
            $settings = PaymentSetting::where('company_id', $companyId)->first();

            $cashRegisterId = $data['cash_register_id']
                ?? $settings?->default_cash_register_id
                ?? $this->getDefaultCashRegister($companyId);

            if (!$cashRegisterId) {
                throw new \RuntimeException('Aucune caisse disponible. Veuillez configurer une caisse par défaut ou sélectionner une caisse.');
            }

            // Validate cash register belongs to company
            $cashRegister = CashRegister::where('id', $cashRegisterId)
                ->where('company_id', $companyId)
                ->firstOrFail();

            // Check for active session
            $activeSession = CashRegisterSession::where('cash_register_id', $cashRegisterId)
                ->where('status', 'open')
                ->first();

            if (!$activeSession) {
                throw new \RuntimeException('Aucune session de caisse ouverte. Veuillez ouvrir une session avant d\'enregistrer un paiement en espèces.');
            }

            // Create cash transaction
            $cashTransaction = CashTransaction::create([
                'company_id' => $companyId,
                'cash_register_id' => $cashRegisterId,
                'session_id' => $activeSession->id,
                'user_id' => Auth::id(),
                'payment_id' => $payment->id,
                'type' => 'in',
                'amount' => $payment->amount,
                'payment_method' => 'cash',
                'reference' => $payment->reference,
                'description' => "Paiement document #{$payment->id}",
                'transactionable_type' => Payment::class,
                'transactionable_id' => $payment->id,
                'transaction_date' => $payment->payment_date,
            ]);

            // Update session expected closing balance
            $activeSession->increment('expected_closing_balance', $payment->amount);

            // Update cash register current balance
            $cashRegister->increment('current_balance', $payment->amount);

            $payment->update(['cash_transaction_id' => $cashTransaction->id]);

            Log::debug('Cash payment processed', [
                'payment_id' => $payment->id,
                'cash_register_id' => $cashRegisterId,
                'amount' => $payment->amount,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new \RuntimeException('Caisse introuvable.');
        } catch (\Throwable $e) {
            Log::error('Cash payment processing error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException($e->getMessage());
        }
    }

    /**
     * Process documentary payment (cheque/LCN) - creates PaymentDocument
     */
    protected function processDocumentaryPayment(Payment $payment, array $data): void
    {
        try {
            if (!isset($data['document_number']) || empty($data['document_number'])) {
                throw new \InvalidArgumentException('Le numéro de document est requis pour ce mode de paiement.');
            }

            if (!isset($data['due_date']) || empty($data['due_date'])) {
                throw new \InvalidArgumentException('La date d\'échéance est requise pour ce mode de paiement.');
            }

            $paymentDocument = PaymentDocument::create([
                'company_id' => $payment->company_id,
                'customer_id' => $payment->customer_id,
                'payment_id' => $payment->id,
                'document_id' => $payment->id, // Use payment ID as document reference
                'type' => $payment->payment_mode, // 'cheque' or 'lcn'
                'number' => $data['document_number'],
                'due_date' => $data['due_date'],
                'amount' => $payment->amount,
                'drawer_name' => $data['drawer_name'] ?? null,
                'drawer_bank' => $data['drawer_bank'] ?? null,
                'drawer_account' => $data['drawer_account'] ?? null,
                'drawer_address' => $data['drawer_address'] ?? null,
                'beneficiary_name' => $data['beneficiary_name'] ?? null,
                'status' => 'pending',
                'notes' => $data['document_notes'] ?? null,
            ]);

            $payment->update(['payment_document_id' => $paymentDocument->id]);

            Log::debug('Documentary payment processed', [
                'payment_id' => $payment->id,
                'document_number' => $data['document_number'],
                'amount' => $payment->amount,
            ]);
        } catch (\Throwable $e) {
            Log::error('Documentary payment processing error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process direct payment (virement/carte) - no additional records needed
     */
    protected function processDirectPayment(Payment $payment, array $data): void
    {
        Log::debug('Direct payment processed', [
            'payment_id' => $payment->id,
            'payment_mode' => $payment->payment_mode,
        ]);
    }

    /**
     * Create document relationship for payment allocation
     */
    protected function createDocumentRelationship(Document $invoiceDocument, Payment $payment): void
    {
        try {
            // Create a virtual payment document for tracking
            $paymentDoc = Document::create([
                'company_id' => $payment->company_id,
                'customer_id' => $payment->customer_id,
                'number' => 'PAY-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                'document_date' => $payment->payment_date,
                'total_ht' => $payment->amount,
                'total_tva' => 0,
                'total_ttc' => $payment->amount,
                'notes' => "Paiement {$payment->payment_mode_label}",
                'documentable_type' => Payment::class,
                'documentable_id' => $payment->id,
            ]);

            $relationship = DocumentRelationship::create([
                'parent_document_id' => $invoiceDocument->id,
                'child_document_id' => $paymentDoc->id,
                'relationship_type' => 'payment',
                'allocated_amount' => $payment->amount,
            ]);

            $payment->update(['document_relationship_id' => $relationship->id]);

            Log::debug('Document relationship created', [
                'payment_id' => $payment->id,
                'parent_document_id' => $invoiceDocument->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Document relationship creation error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            // Non-critical error, don't throw
        }
    }

    /**
     * Record payment in workflow history
     */
    protected function recordPaymentHistory(Document $document, Payment $payment): void
    {
        try {
            DocumentWorkflowHistory::create([
                'document_id' => $document->id,
                'event' => 'payment_received',
                'from_status' => $document->documentable->getStatus(),
                'to_status' => $document->documentable->getStatus(),
                'metadata' => [
                    'payment_id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'payment_mode' => $payment->payment_mode,
                    'payment_date' => $payment->payment_date?->format('Y-m-d'),
                ],
                'triggered_by' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record payment history', [
                'document_id' => $document->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            // Non-critical error, don't throw
        }
    }

    /**
     * Cancel a payment
     */
    public function cancelPayment(int $paymentId): Payment
    {
        try {
            $payment = Payment::with(['payable.document', 'cashTransaction', 'paymentDocument'])
                ->where('company_id', $this->getCompanyId())
                ->findOrFail($paymentId);

            if ($payment->status === 'cancelled') {
                throw new \InvalidArgumentException('Ce paiement est déjà annulé.');
            }

            $payable = $payment->payable;
            if (!($payable instanceof PayableInterface)) {
                throw new \InvalidArgumentException('Document payable invalide.');
            }

            DB::beginTransaction();

            try {
                $document = $payable->getDocument();

                // Reverse cash transaction if exists
                if ($payment->cashTransaction) {
                    $this->reverseCashTransaction($payment->cashTransaction);
                }

                // Release payment document if exists
                if ($payment->paymentDocument) {
                    $payment->paymentDocument->update([
                        'payment_id' => null,
                        'status' => 'cancelled',
                    ]);
                }

                // Delete document relationship
                if ($payment->documentRelationship) {
                    $childDoc = $payment->documentRelationship->childDocument;
                    $payment->documentRelationship->delete();
                    if ($childDoc) {
                        $childDoc->delete();
                    }
                }

                $payment->markAsCancelled();

                // Update document status if it was fully paid
                if ($payable->getStatus() === 'PAID' && !$payable->isFullyPaid()) {
                    $this->revertDocumentStatus($payable);
                }

                DB::commit();

                Log::info('Payment cancelled successfully', ['payment_id' => $paymentId]);

                return $payment->fresh();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error("Payment cancellation transaction failed", [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new \InvalidArgumentException('Paiement introuvable.');
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Unexpected error during payment cancellation', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Une erreur est survenue lors de l\'annulation du paiement.');
        }
    }

    /**
     * Revert document status when payment is cancelled
     */
    protected function revertDocumentStatus(PayableInterface $payable): void
    {
        if ($payable instanceof Invoice) {
            $payable->markAsSent();
        } elseif ($payable instanceof Deposit) {
            $payable->setStatus('FINALIZED');
        } elseif ($payable instanceof BalanceInvoice) {
            $payable->setStatus('SENT');
        }
    }

    /**
     * Reverse a cash transaction
     */
    protected function reverseCashTransaction(CashTransaction $transaction): void
    {
        try {
            $cashRegister = $transaction->cashRegister;

            // Create a reversing transaction
            $reversingTransaction = CashTransaction::create([
                'company_id' => $transaction->company_id,
                'cash_register_id' => $transaction->cash_register_id,
                'session_id' => $transaction->session_id,
                'user_id' => Auth::id(),
                'type' => 'out',
                'amount' => $transaction->amount,
                'payment_method' => $transaction->payment_method,
                'reference' => 'ANNUL-' . ($transaction->reference ?? $transaction->id),
                'description' => "Annulation: {$transaction->description}",
                'transactionable_type' => $transaction->transactionable_type,
                'transactionable_id' => $transaction->transactionable_id,
                'transaction_date' => now(),
            ]);

            // Update session expected closing balance
            if ($transaction->session) {
                $transaction->session->decrement('expected_closing_balance', $transaction->amount);
            }

            // Update cash register current balance
            if ($cashRegister) {
                $cashRegister->decrement('current_balance', $transaction->amount);
            }

            Log::debug('Cash transaction reversed', [
                'original_transaction_id' => $transaction->id,
                'reversing_transaction_id' => $reversingTransaction->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to reverse cash transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Impossible d\'annuler la transaction de caisse.');
        }
    }

    /**
     * Get payments for a document
     */
    public function getDocumentPayments(int $documentId): \Illuminate\Database\Eloquent\Collection
    {
        try {
            $companyId = $this->getCompanyId();

            $document = Document::where('company_id', $companyId)
                ->where('id', $documentId)
                ->firstOrFail();

            $payable = $document->documentable;

            if (!($payable instanceof PayableInterface)) {
                throw new \InvalidArgumentException('Ce type de document ne supporte pas les paiements.');
            }

            return $payable->completedPayments()
                ->with(['cashTransaction.session', 'paymentDocument.bankRemittance'])
                ->get();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new \InvalidArgumentException('Document introuvable.');
        }
    }

    /**
     * Get payment summary for a document
     */
    public function getPaymentSummary(int $documentId): array
    {
        try {
            $companyId = $this->getCompanyId();

            $document = Document::where('company_id', $companyId)
                ->where('id', $documentId)
                ->firstOrFail();

            $payable = $document->documentable;

            if (!($payable instanceof PayableInterface)) {
                throw new \InvalidArgumentException('Ce type de document ne supporte pas les paiements.');
            }

            return $payable->getPaymentSummary();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new \InvalidArgumentException('Document introuvable.');
        }
    }

    /**
     * Validate payment eligibility
     */
    protected function validatePaymentEligibility(PayableInterface $payable): void
    {
        if (!$payable->isPayable()) {
            throw new \InvalidArgumentException('Ce document ne peut pas recevoir de paiements.');
        }

        if (!$payable->isEligibleForPayment()) {
            $currentStatus = $payable->getStatus();
            $eligibleStatuses = implode(', ', $payable->getPaymentEligibleStatuses());
            throw new \InvalidArgumentException("Le document doit avoir l'un des statuts suivants pour recevoir un paiement: {$eligibleStatuses}. Statut actuel: {$currentStatus}");
        }
    }

    /**
     * Validate payment amount
     */
    protected function validatePaymentAmount(PayableInterface $payable, float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant du paiement doit être supérieur à zéro.');
        }

        if (!$this->isOverpaymentAllowed()) {
            $remaining = $payable->getRemainingAmount();
            if ($amount > $remaining) {
                throw new \InvalidArgumentException("Le montant du paiement ({$amount}) ne peut pas dépasser le montant restant ({$remaining}).");
            }
        }
    }

    protected function isOverpaymentAllowed(): bool
    {
        return PaymentSetting::isOverpaymentAllowed($this->getCompanyId());
    }

    protected function shouldAutoMarkPaid(int $companyId): bool
    {
        return PaymentSetting::shouldAutoMarkInvoicePaid($companyId);
    }

    protected function getDefaultCashRegister(int $companyId): ?int
    {
        return CashRegister::where('company_id', $companyId)
            ->where('is_active', true)
            ->value('id');
    }

    protected function getCompanyId(): int
    {
        return config('app.current_company_id')
            ?? throw new \RuntimeException('Aucune entreprise sélectionnée.');
    }

    /**
     * Sanitize payment data for logging
     */
    protected function sanitizePaymentData(array $data): array
    {
        $sanitized = $data;
        // Remove sensitive data if any
        unset($sanitized['notes'], $sanitized['document_notes']);
        return $sanitized;
    }
}
