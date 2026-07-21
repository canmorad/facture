<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\PurchaseOrder;
use App\Models\DeliveryNote;
use App\Models\CreditNote;
use App\Models\Deposit;
use App\Models\DocumentRelationship;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\ImmutableDocumentException;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentWorkflowService
{
    public function __construct(
        protected DocumentRepositoryInterface $documentRepository,
        protected FiscalComplianceService $fiscalService,
        protected NumberingService $numberingService,
        protected DocumentService $documentService,
        protected DocumentItemService $documentItemService,
        protected DocumentCalculationService $calculationService
    ) {}

    public function transitionState(Document $document, string $newStatus, ?array $metadata = null): Document
    {
        $this->fiscalService->guardForEdit($document);

        DB::beginTransaction();

        try {
            $documentable = $document->documentable;

            if ($documentable && method_exists($documentable, 'transitionTo')) {
                $documentable->transitionTo($newStatus);
            }

            $this->recordWorkflowHistory($document, 'STATE_TRANSITION', $document->documentable?->getAttribute('status') ?? 'DRAFT', $newStatus, $metadata);

            if ($newStatus === 'FINALIZED' && !$document->number) {
                $this->assignSequentialNumber($document);
                $this->fiscalService->lockDocument($document, 'finalized_document_immutable');
            }

            DB::commit();

            return $document->fresh($this->documentRepository->getDefaultRelations());
        } catch (InvalidStatusTransitionException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Workflow transition error', [
                'document_id' => $document->id,
                'to_status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function convertQuoteToInvoice(int $quoteId, string $invoiceType = 'STANDARD', ?int $companyId = null): Document
    {
        $sourceDocument = $this->documentRepository->findWithRelations($quoteId);

        if (!$sourceDocument || $sourceDocument->documentable_type !== Quote::class) {
            throw new \InvalidArgumentException('Source document must be a Quote');
        }

        $quoteStatus = $sourceDocument->documentable->getAttribute('status');
        if (!in_array($quoteStatus, ['SIGNED', 'FINALIZED', 'SENT'])) {
            throw new \InvalidArgumentException('Quote must be signed, finalized, or sent before conversion');
        }

        $companyId = $companyId ?? $sourceDocument->company_id;

        DB::beginTransaction();

        try {
            if ($invoiceType === 'SOLDE') {
                $newDocument = $this->createSoldeInvoice($sourceDocument, $companyId);
            } else {
                $newDocument = $this->createStandardInvoiceFromQuote($sourceDocument, $companyId, $invoiceType);
            }

            DocumentRelationship::create([
                'parent_document_id' => $sourceDocument->id,
                'child_document_id' => $newDocument->id,
                'relationship_type' => 'CONVERTED_TO',
                'allocated_amount' => $newDocument->total_ttc,
            ]);

            $this->recordWorkflowHistory($sourceDocument, 'CONVERTED_TO_INVOICE', null, null, [
                'target_invoice_id' => $newDocument->id,
                'invoice_type' => $invoiceType,
            ]);

            DB::commit();

            return $newDocument->fresh($this->documentRepository->getDefaultRelations());
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function convertQuoteToPurchaseOrder(int $quoteId, ?int $companyId = null): Document
    {
        $sourceDocument = $this->documentRepository->findWithRelations($quoteId);

        if (!$sourceDocument || $sourceDocument->documentable_type !== Quote::class) {
            throw new \InvalidArgumentException('Source document must be a Quote');
        }

        $companyId = $companyId ?? $sourceDocument->company_id;

        DB::beginTransaction();

        try {
            $purchaseOrder = PurchaseOrder::create([
                'status' => 'DRAFT',
                'expected_date' => null,
            ]);

            // RÈGLE COMPTABLE STRICTE : Pas de numéro pour les brouillons
            // Le numéro sera généré UNIQUEMENT lorsque l'utilisateur cliquera sur "Finaliser"
            $newDocument = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $sourceDocument->customer_id,
                'bank_account_id' => $sourceDocument->bank_account_id,
                'parent_document_id' => $sourceDocument->id,
                'number' => null,  // NULL pour les brouillons - jamais de numéro officiel
                'total_ht' => $sourceDocument->total_ht,
                'total_tva' => $sourceDocument->total_tva,
                'total_ttc' => $sourceDocument->total_ttc,
                'global_discount_type' => $sourceDocument->global_discount_type,
                'global_discount_value' => $sourceDocument->global_discount_value,
                'global_discount_amount' => $sourceDocument->global_discount_amount,
                'notes' => $sourceDocument->notes,
                'terms' => $sourceDocument->terms,
                'intro_text' => $sourceDocument->intro_text,
                'footer_text' => $sourceDocument->footer_text,
                'conclusion_text' => $sourceDocument->conclusion_text,
                'documentable_type' => PurchaseOrder::class,
                'documentable_id' => $purchaseOrder->id,
                'payment_condition' => $sourceDocument->payment_condition,
                'payment_mode' => $sourceDocument->payment_mode,
                'late_fee_interest' => $sourceDocument->late_fee_interest,
            ]);

            foreach ($sourceDocument->items as $item) {
                $newDocument->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'product_type' => $item->product_type,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'total_ht' => $item->total_ht,
                    'total_ttc' => $item->total_ttc,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                ]);
            }

            // NOTE : Le document reste en statut DRAFT
            // L'utilisateur devra cliquer sur "Finaliser" pour obtenir un numéro officiel

            DocumentRelationship::create([
                'parent_document_id' => $sourceDocument->id,
                'child_document_id' => $newDocument->id,
                'relationship_type' => 'CONVERTED_TO_PO',
            ]);

            $this->recordWorkflowHistory($sourceDocument, 'CONVERTED_TO_PO', null, null, [
                'target_po_id' => $newDocument->id,
            ]);

            DB::commit();

            return $newDocument->fresh($this->documentRepository->getDefaultRelations());
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function convertToDeliveryNote(int $sourceId, string $sourceType, ?int $companyId = null): Document
    {
        $sourceDocument = $this->documentRepository->findWithRelations($sourceId);

        if (!$sourceDocument) {
            throw new \InvalidArgumentException('Source document not found');
        }

        $companyId = $companyId ?? $sourceDocument->company_id;

        DB::beginTransaction();

        try {
            $deliveryNote = DeliveryNote::create([
                'status' => 'DRAFT',
                'delivery_date' => now()->toDateString(),
            ]);

            $newDocument = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $sourceDocument->customer_id,
                'bank_account_id' => $sourceDocument->bank_account_id,
                'parent_document_id' => $sourceDocument->id,
                'number' => null,
                'total_ht' => $sourceDocument->total_ht,
                'total_tva' => $sourceDocument->total_tva,
                'total_ttc' => $sourceDocument->total_ttc,
                'global_discount_type' => $sourceDocument->global_discount_type,
                'global_discount_value' => $sourceDocument->global_discount_value,
                'global_discount_amount' => $sourceDocument->global_discount_amount,
                'notes' => $sourceDocument->notes,
                'terms' => $sourceDocument->terms,
                'intro_text' => $sourceDocument->intro_text,
                'footer_text' => $sourceDocument->footer_text,
                'conclusion_text' => $sourceDocument->conclusion_text,
                'documentable_type' => DeliveryNote::class,
                'documentable_id' => $deliveryNote->id,
                'payment_condition' => $sourceDocument->payment_condition,
                'payment_mode' => $sourceDocument->payment_mode,
                'late_fee_interest' => $sourceDocument->late_fee_interest,
            ]);

            foreach ($sourceDocument->items as $item) {
                $newDocument->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'product_type' => $item->product_type,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'total_ht' => $item->total_ht,
                    'total_ttc' => $item->total_ttc,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                ]);
            }

            DocumentRelationship::create([
                'parent_document_id' => $sourceDocument->id,
                'child_document_id' => $newDocument->id,
                'relationship_type' => 'CONVERTED_TO_DN',
            ]);

            $this->recordWorkflowHistory($sourceDocument, 'CONVERTED_TO_DN', null, null, [
                'target_dn_id' => $newDocument->id,
            ]);

            DB::commit();

            return $newDocument->fresh($this->documentRepository->getDefaultRelations());
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function convertDeliveryNoteToInvoice(int $deliveryNoteId, string $invoiceType = 'STANDARD'): Document
    {
        $sourceDocument = $this->documentRepository->findWithRelations($deliveryNoteId);

        if (!$sourceDocument || $sourceDocument->documentable_type !== DeliveryNote::class) {
            throw new \InvalidArgumentException('Source document must be a Delivery Note');
        }

        $companyId = $sourceDocument->company_id;

        DB::beginTransaction();

        try {
            $invoice = Invoice::create([
                'status' => 'DRAFT',
                'due_date' => now()->addDays(30)->toDateString(),
                'type' => $invoiceType,
            ]);

            // RÈGLE COMPTABLE STRICTE : Pas de numéro pour les brouillons
            // Le numéro sera généré UNIQUEMENT lorsque l'utilisateur cliquera sur "Finaliser"
            $newDocument = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $sourceDocument->customer_id,
                'bank_account_id' => $sourceDocument->bank_account_id,
                'parent_document_id' => $sourceDocument->id,
                'number' => null,  // NULL pour les brouillons - jamais de numéro officiel
                'total_ht' => $sourceDocument->total_ht,
                'total_tva' => $sourceDocument->total_tva,
                'total_ttc' => $sourceDocument->total_ttc,
                'global_discount_type' => $sourceDocument->global_discount_type,
                'global_discount_value' => $sourceDocument->global_discount_value,
                'global_discount_amount' => $sourceDocument->global_discount_amount,
                'notes' => $sourceDocument->notes,
                'terms' => $sourceDocument->terms,
                'intro_text' => $sourceDocument->intro_text,
                'footer_text' => $sourceDocument->footer_text,
                'conclusion_text' => $sourceDocument->conclusion_text,
                'documentable_type' => Invoice::class,
                'documentable_id' => $invoice->id,
                'payment_condition' => $sourceDocument->payment_condition,
                'payment_mode' => $sourceDocument->payment_mode,
                'late_fee_interest' => $sourceDocument->late_fee_interest,
            ]);

            foreach ($sourceDocument->items as $item) {
                $newDocument->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'product_type' => $item->product_type,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'total_ht' => $item->total_ht,
                    'total_ttc' => $item->total_ttc,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                ]);
            }

            // NOTE : Le document reste en statut DRAFT
            // L'utilisateur devra cliquer sur "Finaliser" pour obtenir un numéro officiel

            DocumentRelationship::create([
                'parent_document_id' => $sourceDocument->id,
                'child_document_id' => $newDocument->id,
                'relationship_type' => 'CONVERTED_TO_INVOICE',
                'allocated_amount' => $newDocument->total_ttc,
            ]);

            $this->recordWorkflowHistory($sourceDocument, 'CONVERTED_TO_INVOICE', null, null, [
                'target_invoice_id' => $newDocument->id,
                'invoice_type' => $invoiceType,
            ]);

            DB::commit();

            return $newDocument->fresh($this->documentRepository->getDefaultRelations());
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function createCreditNoteForInvoice(int $invoiceId, string $reason, array $items, string $type = 'STANDARD'): Document
    {
        $sourceDocument = $this->documentRepository->findWithRelations($invoiceId);

        if (!$sourceDocument || $sourceDocument->documentable_type !== Invoice::class) {
            throw new \InvalidArgumentException('Source document must be an Invoice');
        }

        $invoice = $sourceDocument->documentable;
        if (!in_array($invoice->getAttribute('status'), ['FINALIZED', 'SENT', 'PAID', 'OVERDUE'])) {
            throw new \InvalidArgumentException('Invoice must be finalized, sent, paid, or overdue');
        }

        $companyId = $sourceDocument->company_id;

        $calculated = $this->calculationService->calculate(
            $items,
            null,
            null
        );

        DB::beginTransaction();

        try {
            $creditNote = CreditNote::create([
                'type' => $type,
                'reason' => $reason,
                'status' => 'DRAFT',
            ]);

            // RÈGLE COMPTABLE STRICTE : Pas de numéro pour les brouillons
            // Le numéro sera généré UNIQUEMENT lorsque l'utilisateur cliquera sur "Finaliser"
            $newDocument = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $sourceDocument->customer_id,
                'bank_account_id' => $sourceDocument->bank_account_id,
                'parent_document_id' => $sourceDocument->id,
                'number' => null,  // NULL pour les brouillons - jamais de numéro officiel
                'total_ht' => abs($calculated['total_ht']),
                'total_tva' => abs($calculated['total_tva']),
                'total_ttc' => abs($calculated['total_ttc']),
                'global_discount_type' => null,
                'global_discount_value' => 0,
                'global_discount_amount' => 0,
                'notes' => "Avoir pour facture {$sourceDocument->number}",
                'terms' => $sourceDocument->terms,
                'intro_text' => $sourceDocument->intro_text,
                'footer_text' => $sourceDocument->footer_text,
                'conclusion_text' => $sourceDocument->conclusion_text,
                'documentable_type' => CreditNote::class,
                'documentable_id' => $creditNote->id,
                'payment_condition' => $sourceDocument->payment_condition,
                'payment_mode' => $sourceDocument->payment_mode,
                'late_fee_interest' => $sourceDocument->late_fee_interest,
            ]);

            foreach ($items as $idx => $item) {
                $processed = $calculated['processed_items'][$idx];
                $newDocument->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['designation'],
                    'product_type' => null,
                    'quantity' => abs($item['quantity']),
                    'unit_price' => abs($item['unit_price']),
                    'tax_rate' => $item['tax_rate'],
                    'total_ht' => abs($processed['line_ht']),
                    'total_ttc' => abs($processed['line_ht'] * (1 + $item['tax_rate'] / 100)),
                    'discount_type' => null,
                    'discount_value' => 0,
                ]);
            }

            // NOTE : Le document reste en statut DRAFT
            // L'utilisateur devra cliquer sur "Finaliser" pour obtenir un numéro officiel

            $invoice->update(['has_credit_note' => true]);

            DocumentRelationship::create([
                'parent_document_id' => $sourceDocument->id,
                'child_document_id' => $newDocument->id,
                'relationship_type' => 'CREDIT_NOTE',
                'allocated_amount' => $newDocument->total_ttc,
            ]);

            $this->recordWorkflowHistory($sourceDocument, 'CREDIT_NOTE_CREATED', null, null, [
                'credit_note_id' => $newDocument->id,
                'reason' => $reason,
            ]);

            DB::commit();

            return $newDocument->fresh($this->documentRepository->getDefaultRelations());
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getAvailableTransitions(Document $document): array
    {
        if (!$document->documentable) {
            return [];
        }

        $status = $document->documentable->getAttribute('status') ?? 'DRAFT';

        $transitions = match ($document->documentable_type) {
            Quote::class => [
                'DRAFT' => ['FINALIZED'],
                'FINALIZED' => ['SENT', 'SIGNED', 'EXPIRED'],
                'SENT' => ['SIGNED', 'EXPIRED'],
                'SIGNED' => [],
                'EXPIRED' => [],
            ],
            Invoice::class => [
                'DRAFT' => ['FINALIZED'],
                'FINALIZED' => ['SENT', 'PAID'],
                'SENT' => ['PAID', 'OVERDUE', 'CANCELLED'],
                'PAID' => [],
                'OVERDUE' => ['PAID', 'CANCELLED'],
                'CANCELLED' => [],
            ],
            PurchaseOrder::class => [
                'DRAFT' => ['FINALIZED'],
                'FINALIZED' => ['SENT', 'CONFIRMED'],
                'SENT' => ['CONFIRMED', 'CANCELLED'],
                'CONFIRMED' => [],
                'CANCELLED' => [],
            ],
            DeliveryNote::class => [
                'DRAFT' => ['FINALIZED'],
                'FINALIZED' => ['SENT'],
                'SENT' => ['DELIVERED'],
                'DELIVERED' => [],
            ],
            CreditNote::class => [
                'DRAFT' => ['FINALIZED'],
                'FINALIZED' => ['SENT'],
                'SENT' => ['APPLIED'],
                'APPLIED' => [],
            ],
            default => [],
        };

        return $transitions[$status] ?? [];
    }

    public function getAvailableActions(Document $document): array
    {
        $transitions = $this->getAvailableTransitions($document);
        $documentType = $document->documentable_type;
        $status = $document->documentable->getAttribute('status') ?? 'DRAFT';
        $isLocked = $this->fiscalService->isLocked($document);

        $actions = [
            'can_edit' => !$isLocked && in_array($status, ['DRAFT']),
            'can_delete' => !$isLocked && in_array($status, ['DRAFT']) && $document->children()->count() === 0,
            'can_finalize' => in_array('FINALIZED', $transitions),
            'can_send' => in_array('SENT', $transitions),
            'can_sign' => in_array('SIGNED', $transitions),
            'can_mark_paid' => in_array('PAID', $transitions),
            'can_mark_delivered' => in_array('DELIVERED', $transitions),
            'can_confirm' => in_array('CONFIRMED', $transitions),
            'can_cancel' => in_array('CANCELLED', $transitions) && $this->fiscalService->canCancelWithoutCreditNote($document),
            'can_create_credit_note' => $documentType === Invoice::class && in_array($status, ['PAID', 'OVERDUE', 'SENT']),
            'can_convert_to_invoice' => false,
            'can_convert_to_po' => false,
            'can_convert_to_dn' => false,
            'can_duplicate' => true,
            'can_download' => !in_array($status, ['DRAFT']),
        ];

        if ($documentType === Quote::class) {
            $actions['can_convert_to_invoice'] = in_array($status, ['SIGNED', 'FINALIZED', 'SENT']) && !$this->hasChildInvoice($document);
            $actions['can_convert_to_po'] = in_array($status, ['SIGNED']) && !$this->hasChildPO($document);
            $actions['can_convert_to_dn'] = in_array($status, ['SIGNED', 'FINALIZED', 'SENT']);
        }

        if ($documentType === DeliveryNote::class) {
            $actions['can_convert_to_invoice'] = in_array($status, ['DELIVERED', 'FINALIZED']);
        }

        if ($documentType === PurchaseOrder::class) {
            $actions['can_convert_to_dn'] = in_array($status, ['CONFIRMED', 'FINALIZED']);
        }

        return $actions;
    }

    protected function createStandardInvoiceFromQuote(Document $sourceDocument, int $companyId, string $invoiceType): Document
    {
        if ($this->hasChildInvoice($sourceDocument)) {
            throw new \RuntimeException('This quote already has an invoice');
        }

        $invoice = Invoice::create([
            'status' => 'DRAFT',
            'due_date' => null,
            'type' => $invoiceType,
        ]);

        // RÈGLE COMPTABLE STRICTE : Pas de numéro pour les brouillons
        // Le numéro sera généré UNIQUEMENT lorsque l'utilisateur cliquera sur "Finaliser"
        $newDocument = $this->documentService->create([
            'company_id' => $companyId,
            'customer_id' => $sourceDocument->customer_id,
            'bank_account_id' => $sourceDocument->bank_account_id,
            'parent_document_id' => $sourceDocument->id,
            'number' => null,  // NULL pour les brouillons - jamais de numéro officiel
            'total_ht' => $sourceDocument->total_ht,
            'total_tva' => $sourceDocument->total_tva,
            'total_ttc' => $sourceDocument->total_ttc,
            'global_discount_type' => $sourceDocument->global_discount_type,
            'global_discount_value' => $sourceDocument->global_discount_value,
            'global_discount_amount' => $sourceDocument->global_discount_amount,
            'notes' => $sourceDocument->notes,
            'terms' => $sourceDocument->terms,
            'intro_text' => $sourceDocument->intro_text,
            'footer_text' => $sourceDocument->footer_text,
            'conclusion_text' => $sourceDocument->conclusion_text,
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoice->id,
            'payment_condition' => $sourceDocument->payment_condition,
            'payment_mode' => $sourceDocument->payment_mode,
            'late_fee_interest' => $sourceDocument->late_fee_interest,
        ]);

        foreach ($sourceDocument->items as $item) {
            $newDocument->items()->create([
                'product_id' => $item->product_id,
                'description' => $item->description,
                'product_type' => $item->product_type,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate,
                'total_ht' => $item->total_ht,
                'total_ttc' => $item->total_ttc,
                'discount_type' => $item->discount_type,
                'discount_value' => $item->discount_value,
            ]);
        }

        // NOTE : Le document reste en statut DRAFT
        // L'utilisateur devra cliquer sur "Finaliser" pour obtenir un numéro officiel

        return $newDocument;
    }

    protected function createSoldeInvoice(Document $sourceDocument, int $companyId): Document
    {
        if ($this->hasChildSoldeInvoice($sourceDocument)) {
            throw new \RuntimeException('This quote already has a solde invoice');
        }

        $paidDepositsTotal = Deposit::where('quote_id', $sourceDocument->documentable_id)
            ->where('status', 'PAID')
            ->sum('input_value');

        $quoteTotalTtc = $sourceDocument->total_ttc;
        $remainingBalance = $quoteTotalTtc - $paidDepositsTotal;

        if ($remainingBalance <= 0) {
            throw new \RuntimeException('No remaining balance to invoice');
        }

        $invoice = Invoice::create([
            'status' => 'DRAFT',
            'due_date' => null,
            'type' => 'SOLDE',
        ]);

        // RÈGLE COMPTABLE STRICTE : Pas de numéro pour les brouillons
        // Le numéro sera généré UNIQUEMENT lorsque l'utilisateur cliquera sur "Finaliser"
        $newDocument = $this->documentService->create([
            'company_id' => $companyId,
            'customer_id' => $sourceDocument->customer_id,
            'bank_account_id' => $sourceDocument->bank_account_id,
            'parent_document_id' => $sourceDocument->id,
            'number' => null,  // NULL pour les brouillons - jamais de numéro officiel
            'total_ht' => $remainingBalance,
            'total_tva' => 0,
            'total_ttc' => $remainingBalance,
            'global_discount_type' => null,
            'global_discount_value' => 0,
            'global_discount_amount' => 0,
            'notes' => 'Solde - Acomptes réglés : ' . number_format($paidDepositsTotal, 2) . ' DH',
            'terms' => $sourceDocument->terms,
            'intro_text' => $sourceDocument->intro_text,
            'footer_text' => $sourceDocument->footer_text,
            'conclusion_text' => $sourceDocument->conclusion_text,
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoice->id,
            'payment_condition' => $sourceDocument->payment_condition,
            'payment_mode' => $sourceDocument->payment_mode,
            'late_fee_interest' => $sourceDocument->late_fee_interest,
        ]);

        $newDocument->items()->create([
            'product_id' => null,
            'description' => 'Solde (TTC: ' . number_format($quoteTotalTtc, 2) . ' - Acomptes: ' . number_format($paidDepositsTotal, 2) . ')',
            'product_type' => null,
            'quantity' => 1,
            'unit_price' => $remainingBalance,
            'tax_rate' => 0,
            'total_ht' => $remainingBalance,
            'total_ttc' => $remainingBalance,
            'discount_type' => null,
            'discount_value' => 0,
        ]);

        // NOTE : Le document reste en statut DRAFT
        // L'utilisateur devra cliquer sur "Finaliser" pour obtenir un numéro officiel

        return $newDocument;
    }

    protected function assignSequentialNumber(Document $document): void
    {
        $documentType = match ($document->documentable_type) {
            Invoice::class => 'invoice',
            Quote::class => 'quote',
            CreditNote::class => 'credit_note',
            PurchaseOrder::class => 'purchase_order',
            DeliveryNote::class => 'delivery_note',
            default => null,
        };

        if (!$documentType) {
            return;
        }

        $number = $this->numberingService->generateNumber($documentType, $document->company_id);
        $this->documentService->updateNumber($document, $number);
    }

    protected function recordWorkflowHistory(Document $document, string $event, ?string $fromStatus, ?string $toStatus, ?array $metadata = null): void
    {
        try {
            DB::table('document_workflow_history')->insert([
                'document_id' => $document->id,
                'event' => $event,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'metadata' => $metadata ? json_encode($metadata) : null,
                'triggered_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record workflow history', [
                'document_id' => $document->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function hasChildInvoice(Document $document): bool
    {
        return $document->children()
            ->where('documentable_type', Invoice::class)
            ->whereHasMorph('documentable', [Invoice::class], fn ($q) => $q->whereIn('type', ['STANDARD', 'ACOMPTE']))
            ->exists();
    }

    protected function hasChildSoldeInvoice(Document $document): bool
    {
        return $document->children()
            ->where('documentable_type', Invoice::class)
            ->whereHasMorph('documentable', [Invoice::class], fn ($q) => $q->where('type', 'SOLDE'))
            ->exists();
    }

    protected function hasChildPO(Document $document): bool
    {
        return $document->children()
            ->where('documentable_type', PurchaseOrder::class)
            ->exists();
    }

    public function consolidateDeliveryNotesToInvoice(array $deliveryNoteIds, string $invoiceType = 'STANDARD'): Document
    {
        if (count($deliveryNoteIds) < 2) {
            throw new \InvalidArgumentException('Au moins 2 bons de livraison sont requis pour la consolidation.');
        }

        $companyId = $this->getCompanyId();

        $documents = Document::where('company_id', $companyId)
            ->whereIn('id', $deliveryNoteIds)
            ->where('documentable_type', DeliveryNote::class)
            ->with(['documentable', 'customer', 'items'])
            ->get();

        if ($documents->count() !== count($deliveryNoteIds)) {
            throw new \InvalidArgumentException('Certains bons de livraison sont introuvables ou non autorisés.');
        }

        $customerId = $documents->first()->customer_id;
        if ($documents->pluck('customer_id')->unique()->count() > 1) {
            throw new \InvalidArgumentException('Tous les bons de livraison doivent appartenir au même client.');
        }

        foreach ($documents as $doc) {
            $status = $doc->documentable->getAttribute('status');
            if (!in_array($status, ['DELIVERED', 'FINALIZED'])) {
                throw new \InvalidArgumentException("Le bon de livraison #{$doc->id} n'est pas livré.");
            }
        }

        DB::beginTransaction();

        try {
            $totalHt = $documents->sum('total_ht');
            $totalTva = $documents->sum('total_tva');
            $totalTtc = $documents->sum('total_ttc');

            $invoice = Invoice::create([
                'status' => 'DRAFT',
                'due_date' => now()->addDays(30)->toDateString(),
                'type' => $invoiceType,
            ]);

            // RÈGLE COMPTABLE STRICTE : Pas de numéro pour les brouillons
            // Le numéro sera généré UNIQUEMENT lorsque l'utilisateur cliquera sur "Finaliser"
            $consolidatedDocument = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'bank_account_id' => $documents->first()->bank_account_id,
                'parent_document_id' => null,
                'number' => null,  // NULL pour les brouillons - jamais de numéro officiel
                'total_ht' => $totalHt,
                'total_tva' => $totalTva,
                'total_ttc' => $totalTtc,
                'global_discount_type' => null,
                'global_discount_value' => 0,
                'global_discount_amount' => 0,
                'notes' => 'Facture consolidée à partir de ' . count($documents) . ' bons de livraison.',
                'terms' => $documents->first()->terms,
                'intro_text' => $documents->first()->intro_text,
                'footer_text' => $documents->first()->footer_text,
                'conclusion_text' => $documents->first()->conclusion_text,
                'documentable_type' => Invoice::class,
                'documentable_id' => $invoice->id,
                'payment_condition' => $documents->first()->payment_condition,
                'payment_mode' => $documents->first()->payment_mode,
                'late_fee_interest' => $documents->first()->late_fee_interest,
            ]);

            $itemGroup = [];
            foreach ($documents as $doc) {
                foreach ($doc->items as $item) {
                    $key = md5($item->description . $item->unit_price . $item->tax_rate);
                    if (!isset($itemGroup[$key])) {
                        $itemGroup[$key] = [
                            'product_id' => $item->product_id,
                            'description' => $item->description,
                            'product_type' => $item->product_type,
                            'unit_price' => $item->unit_price,
                            'tax_rate' => $item->tax_rate,
                            'discount_type' => $item->discount_type,
                            'discount_value' => $item->discount_value,
                            'quantity' => 0,
                            'total_ht' => 0,
                            'total_ttc' => 0,
                        ];
                    }
                    $itemGroup[$key]['quantity'] += $item->quantity;
                    $itemGroup[$key]['total_ht'] += $item->total_ht;
                    $itemGroup[$key]['total_ttc'] += $item->total_ttc;
                }
            }

            foreach ($itemGroup as $item) {
                $consolidatedDocument->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'product_type' => $item['product_type'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'total_ht' => $item['total_ht'],
                    'total_ttc' => $item['total_ttc'],
                    'discount_type' => $item['discount_type'],
                    'discount_value' => $item['discount_value'],
                ]);
            }

            foreach ($documents as $doc) {
                DocumentRelationship::create([
                    'parent_document_id' => $doc->id,
                    'child_document_id' => $consolidatedDocument->id,
                    'relationship_type' => 'CONSOLIDATED_INTO',
                    'allocated_amount' => $doc->total_ttc,
                ]);

                $this->recordWorkflowHistory($doc, 'CONSOLIDATED_INTO_INVOICE', null, null, [
                    'target_invoice_id' => $consolidatedDocument->id,
                ]);
            }

            // NOTE : Le document reste en statut DRAFT
            // L'utilisateur devra cliquer sur "Finaliser" pour obtenir un numéro officiel

            DB::commit();

            return $consolidatedDocument->fresh($this->documentRepository->getDefaultRelations());
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function batchConvertToDeliveryNotes(int $sourceId, string $sourceType, array $allocations): array
    {
        $sourceDocument = $this->documentRepository->findWithRelations($sourceId);

        if (!$sourceDocument) {
            throw new \InvalidArgumentException('Document source introuvable.');
        }

        if (!in_array($sourceType, [Quote::class, PurchaseOrder::class])) {
            throw new \InvalidArgumentException('Type de source non valide. Attendu: Quote ou PurchaseOrder.');
        }

        if ($sourceDocument->documentable_type !== $sourceType) {
            throw new \InvalidArgumentException('Le document source n\'est pas du type attendu.');
        }

        $status = $sourceDocument->documentable->getAttribute('status');
        if ($sourceType === Quote::class && !in_array($status, ['SIGNED', 'FINALIZED', 'SENT'])) {
            throw new \InvalidArgumentException('Le devis doit être signé, finalisé ou envoyé.');
        }

        if ($sourceType === PurchaseOrder::class && !in_array($status, ['CONFIRMED', 'FINALIZED'])) {
            throw new \InvalidArgumentException('Le bon de commande doit être confirmé ou finalisé.');
        }

        $totalAllocated = collect($allocations)->sum('allocated_ttc');
        if ($totalAllocated > $sourceDocument->total_ttc) {
            throw new \InvalidArgumentException('Le total alloué dépasse le montant du document source.');
        }

        $companyId = $sourceDocument->company_id;

        DB::beginTransaction();

        try {
            $createdDocuments = [];

            foreach ($allocations as $allocation) {
                $deliveryNote = DeliveryNote::create([
                    'status' => 'DRAFT',
                    'delivery_date' => $allocation['delivery_date'] ?? now()->toDateString(),
                ]);

                $newDocument = $this->documentService->create([
                    'company_id' => $companyId,
                    'customer_id' => $sourceDocument->customer_id,
                    'bank_account_id' => $sourceDocument->bank_account_id,
                    'parent_document_id' => $sourceDocument->id,
                    'number' => null,
                    'total_ht' => $allocation['allocated_ht'],
                    'total_tva' => $allocation['allocated_tva'],
                    'total_ttc' => $allocation['allocated_ttc'],
                    'global_discount_type' => $sourceDocument->global_discount_type,
                    'global_discount_value' => $sourceDocument->global_discount_value,
                    'global_discount_amount' => $sourceDocument->global_discount_amount * ($allocation['allocated_ttc'] / $sourceDocument->total_ttc),
                    'notes' => $sourceDocument->notes,
                    'terms' => $sourceDocument->terms,
                    'intro_text' => $sourceDocument->intro_text,
                    'footer_text' => $sourceDocument->footer_text,
                    'conclusion_text' => $sourceDocument->conclusion_text,
                    'documentable_type' => DeliveryNote::class,
                    'documentable_id' => $deliveryNote->id,
                    'payment_condition' => $sourceDocument->payment_condition,
                    'payment_mode' => $sourceDocument->payment_mode,
                    'late_fee_interest' => $sourceDocument->late_fee_interest,
                ]);

                foreach ($allocation['items'] as $item) {
                    $newDocument->items()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'description' => $item['description'],
                        'product_type' => $item['product_type'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'],
                        'total_ht' => $item['total_ht'],
                        'total_ttc' => $item['total_ttc'],
                        'discount_type' => $item['discount_type'],
                        'discount_value' => $item['discount_value'],
                    ]);
                }

                DocumentRelationship::create([
                    'parent_document_id' => $sourceDocument->id,
                    'child_document_id' => $newDocument->id,
                    'relationship_type' => $sourceType === Quote::class ? 'CONVERTED_TO_DN' : 'CONVERTED_TO_DN',
                    'allocated_amount' => $allocation['allocated_ttc'],
                    'allocated_quantity' => collect($allocation['items'])->sum('quantity'),
                ]);

                $this->recordWorkflowHistory($sourceDocument, 'BATCH_CONVERTED_TO_DN', null, null, [
                    'target_dn_id' => $newDocument->id,
                    'allocated_amount' => $allocation['allocated_ttc'],
                ]);

                $deliveryNote->transitionTo('FINALIZED');

                $createdDocuments[] = $newDocument->fresh($this->documentRepository->getDefaultRelations());
            }

            DB::commit();

            return $createdDocuments;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getDocumentLineage(Document $document): array
    {
        $ancestorChain = $document->getAncestorChain();
        $descendantChain = $document->getDescendantChain();

        $relationships = DocumentRelationship::where('parent_document_id', $document->id)
            ->orWhere('child_document_id', $document->id)
            ->with(['parentDocument', 'childDocument'])
            ->get();

        $linkedDocs = [];
        foreach ($relationships as $rel) {
            if ($rel->parent_document_id !== $document->id) {
                $linkedDocs[] = [
                    'type' => 'parent',
                    'relationship_type' => $rel->relationship_type,
                    'allocated_amount' => $rel->allocated_amount,
                    'allocated_percentage' => $rel->allocation_percentage,
                    'document' => $rel->parentDocument?->load(['documentable', 'customer']),
                ];
            }
            if ($rel->child_document_id !== $document->id) {
                $linkedDocs[] = [
                    'type' => 'child',
                    'relationship_type' => $rel->relationship_type,
                    'allocated_amount' => $rel->allocated_amount,
                    'allocated_percentage' => $rel->allocation_percentage,
                    'document' => $rel->childDocument?->load(['documentable', 'customer']),
                ];
            }
        }

        return [
            'ancestor_chain' => $ancestorChain,
            'descendant_chain' => $descendantChain,
            'direct_relationships' => $linkedDocs,
            'can_create_partial' => $this->canCreatePartialConversion($document),
            'remaining_amount' => $this->getRemainingAmountForConversions($document),
        ];
    }

    protected function canCreatePartialConversion(Document $document): bool
    {
        if (!in_array($document->documentable_type, [Quote::class, PurchaseOrder::class])) {
            return false;
        }

        $status = $document->documentable->getAttribute('status');
        if ($document->documentable_type === Quote::class && !in_array($status, ['SIGNED', 'FINALIZED', 'SENT'])) {
            return false;
        }

        if ($document->documentable_type === PurchaseOrder::class && !in_array($status, ['CONFIRMED', 'FINALIZED'])) {
            return false;
        }

        return $this->getRemainingAmountForConversions($document) > 0;
    }

    protected function getRemainingAmountForConversions(Document $document): float
    {
        $totalAllocated = DocumentRelationship::forParent($document->id)
            ->byTypes(['CONVERTED_TO_DN', 'CONVERTED_TO_PO', 'CONVERTED_TO_INVOICE'])
            ->sum('allocated_amount');

        return max(0, $document->total_ttc - $totalAllocated);
    }

    protected function getCompanyId(): int
    {
        return config('app.current_company_id') ?? throw new \RuntimeException('Aucune entreprise sélectionnée.');
    }
}
