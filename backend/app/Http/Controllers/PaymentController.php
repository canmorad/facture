<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    /**
     * @OA\Get(
     *     path="/api/payments",
     *     summary="Get payments with pagination",
     *     description="Get paginated list of payments with optional filters",
     *     operationId="getPayments",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="document_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="invoice_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="customer_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="payment_mode", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Payments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items()),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-documents');

        try {
            $companyId = $this->getCompanyId();
            $query = Payment::where('company_id', $companyId)
                ->with(['payable.document', 'customer.customerable', 'cashTransaction', 'paymentDocument']);

            // Filters
            if ($request->has('document_id')) {
                $query->whereHas('payable.document', function ($q) use ($request) {
                    $q->where('id', $request->document_id);
                });
            }

            // Legacy filter for backward compatibility
            if ($request->has('invoice_id')) {
                $query->where('invoice_id', $request->invoice_id);
            }

            if ($request->has('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_mode')) {
                $query->where('payment_mode', $request->payment_mode);
            }

            if ($request->has('date_from')) {
                $query->where('payment_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('payment_date', '<=', $request->date_to);
            }

            $perPage = (int) $request->input('per_page', 15);

            return response()->json($query->orderBy('payment_date', 'desc')->paginate($perPage));
        } catch (\Throwable $e) {
            Log::error('Payment index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des paiements.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payments/{id}",
     *     summary="Get a payment by ID",
     *     operationId="getPayment",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Payment retrieved successfully")
     * )
     */
    public function show(int $id): JsonResponse
    {
        Gate::authorize('view-documents');

        try {
            $payment = Payment::where('company_id', $this->getCompanyId())
                ->with([
                    'payable.document',
                    'customer.customerable',
                    'cashTransaction.session.cashRegister',
                    'paymentDocument.bankRemittance',
                    'documentRelationship',
                    'createdBy'
                ])
                ->findOrFail($id);

            return response()->json($payment);
        } catch (\Throwable $e) {
            Log::error("Payment show error ID {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération du paiement.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments",
     *     summary="Create a new payment",
     *     description="Process a payment for a document",
     *     operationId="createPayment",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"document_id", "amount", "payment_date", "payment_mode"},
     *                 @OA\Property(property="document_id", type="integer", description="Document ID to pay"),
     *                 @OA\Property(property="amount", type="number", format="float"),
     *                 @OA\Property(property="payment_date", type="string", format="date"),
     *                 @OA\Property(property="payment_mode", type="string", enum={"CASH", "BANK_TRANSFER", "CHEQUE", "CARD"}),
     *                 @OA\Property(property="reference", type="string", nullable=true),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(property="cash_register_id", type="integer", nullable=true),
     *                 @OA\Property(property="bank_account_id", type="integer", nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="201", description="Payment created successfully")
     * )
     */
    public function store(PaymentRequest $request): JsonResponse
    {
        Gate::authorize('create-documents');

        try {
            $validated = $request->validated();

            // Use document_id for generic payment processing
            $documentId = $validated['document_id'] ?? null;

            if (!$documentId) {
                throw new \InvalidArgumentException('document_id est requis.');
            }

            $payment = $this->paymentService->processPayment($documentId, $validated);

            return response()->json($payment->load(['payable.document', 'cashTransaction', 'paymentDocument']), 201);
        } catch (\Throwable $e) {
            Log::error('Payment creation error: ' . $e->getMessage(), [
                'exception' => $e,
                'input' => $request->all(),
            ]);

            $statusCode = $e instanceof \InvalidArgumentException ? 422 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/payments/{id}",
     *     summary="Cancel a payment",
     *     operationId="cancelPayment",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Payment cancelled successfully")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete-documents');

        try {
            $payment = $this->paymentService->cancelPayment($id);

            return response()->json($payment);
        } catch (\Throwable $e) {
            Log::error("Payment cancellation error ID {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payments/documents/{documentId}/payments",
     *     summary="Get payments for a document",
     *     operationId="getDocumentPayments",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="documentId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Payments retrieved successfully")
     * )
     *
     * Get payments for a document (generic)
     */
    public function getDocumentPayments(int $documentId): JsonResponse
    {
        Gate::authorize('view-documents');

        try {
            $payments = $this->paymentService->getDocumentPayments($documentId);

            return response()->json($payments);
        } catch (\Throwable $e) {
            Log::error("Get document payments error ID {$documentId}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des paiements.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{id}/payments",
     *     summary="Get payments for an invoice",
     *     operationId="getInvoicePayments",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Payments retrieved successfully")
     * )
     *
     * Legacy method for backward compatibility
     */
    public function getByInvoice(int $invoiceId): JsonResponse
    {
        Gate::authorize('view-documents');

        try {
            // Find the document for this invoice
            $companyId = $this->getCompanyId();
            $invoice = \App\Models\Invoice::whereHas('document', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->findOrFail($invoiceId);

            $payments = $this->paymentService->getDocumentPayments($invoice->document->id);

            return response()->json($payments);
        } catch (\Throwable $e) {
            Log::error("Get invoice payments error ID {$invoiceId}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des paiements.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payments/documents/{documentId}/payment-summary",
     *     summary="Get payment summary for a document",
     *     operationId="getDocumentPaymentSummary",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="documentId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Summary retrieved successfully")
     * )
     *
     * Get payment summary for a document (generic)
     */
    public function getPaymentSummary(int $documentId): JsonResponse
    {
        Gate::authorize('view-documents');

        try {
            $summary = $this->paymentService->getPaymentSummary($documentId);

            return response()->json($summary);
        } catch (\Throwable $e) {
            Log::error("Get payment summary error ID {$documentId}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération du résumé de paiement.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payments/create",
     *     summary="Get payment creation data",
     *     operationId="getPaymentCreationData",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Creation data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="cash_registers", type="array", @OA\Items()),
     *             @OA\Property(property="active_sessions", type="object"),
     *             @OA\Property(property="bank_accounts", type="array", @OA\Items()),
     *             @OA\Property(property="has_open_session", type="boolean"),
     *             @OA\Property(property="default_cash_register_id", type="integer", nullable=true)
     *         )
     *     )
     * )
     */
    public function getCreationData(): JsonResponse
    {
        Gate::authorize('create-documents');

        try {
            $companyId = $this->getCompanyId();

            $cashRegisters = \App\Models\CashRegister::where('company_id', $companyId)
                ->active()
                ->with('activeSession')
                ->get();

            $activeSessions = \App\Models\CashRegisterSession::where('company_id', $companyId)
                ->where('status', 'open')
                ->with('cashRegister')
                ->get()
                ->keyBy('cash_register_id');

            $bankAccounts = \App\Models\BankAccount::where('company_id', $companyId)
                ->where('is_active', true)
                ->get();

            $defaultCashRegister = \App\Models\CashRegister::where('company_id', $companyId)
                ->where('is_default', true)
                ->first();

            return response()->json([
                'cash_registers' => $cashRegisters,
                'active_sessions' => $activeSessions,
                'bank_accounts' => $bankAccounts,
                'has_open_session' => $activeSessions->count() > 0,
                'default_cash_register_id' => $defaultCashRegister?->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment creation data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des données de création.',
            ], 500);
        }
    }

    protected function getCompanyId(): int
    {
        return config('app.current_company_id')
            ?? throw new \RuntimeException('Aucune entreprise sélectionnée.');
    }
}
