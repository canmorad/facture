<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Invoice;
use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

    /**
     * @OA\Get(
     *     path="/api/invoices",
     *     summary="Get invoices with pagination",
     *     description="Get paginated list of invoices with optional filters",
     *     operationId="getInvoices",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", default=10, example=10)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         @OA\Schema(type="string", enum={"DRAFT", "FINALIZED", "SENT", "PAID", "OVERDUE", "CANCELLED"})
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by invoice type",
     *         @OA\Schema(type="string", enum={"STANDARD", "ACOMPTE"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter by date from",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter by date to",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         description="Filter by customer ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Invoices retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Invoice")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $perPage = (int) $request->input('per_page', 10);
            $filters = [
                'status' => $request->input('status'),
                'type' => $request->input('type'),
                'search' => $request->input('search'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'customer_id' => $request->input('customer_id'),
            ];

            $invoices = $this->invoiceService->getPaginated($filters, $perPage);
            return response()->json($invoices);
        } catch (\Throwable $e) {
            Log::error('Invoice index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des factures.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{id}",
     *     summary="Get an invoice by ID",
     *     description="Get a specific invoice with all details including relationships",
     *     operationId="getInvoice",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Invoice retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="document", ref="#/components/schemas/Invoice"),
     *             @OA\Property(property="is_derived_from_quote", type="boolean"),
     *             @OA\Property(property="ancestor_chain", type="array", @OA\Items()),
     *             @OA\Property(property="descendant_chain", type="array", @OA\Items()),
     *             @OA\Property(property="available_actions", type="array", @OA\Items()),
     *             @OA\Property(property="available_deductions", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $companyId = $this->getCompanyId();
            $document = Document::where('id', $id)
                ->where('company_id', $companyId)
                ->where('documentable_type', Invoice::class)
                ->with(['customer', 'items', 'documentable', 'parent', 'parent.documentable', 'children.documentable', 'documentable.deductions'])
                ->first();

            if (!$document) {
                $invoice = Invoice::with(['document.customer', 'document.items', 'document.parent', 'document.children.documentable', 'deductions'])->find($id);
                if ($invoice && $invoice->document && $invoice->document->company_id == $companyId) {
                    $document = $invoice->document;
                }
            }

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facture introuvable.',
                ], 404);
            }

            $ancestorChain = $document->getAncestorChain();
            $descendantChain = $document->getDescendantChain();
            $actions = $this->invoiceService->getAvailableActions($document->documentable_id);
            $availableDeductions = $this->invoiceService->getAvailableDeductions($id);

            return response()->json([
                'document' => $document,
                'is_derived_from_quote' => $document->isDerivedFromQuote(),
                'ancestor_chain' => $ancestorChain,
                'descendant_chain' => $descendantChain,
                'available_actions' => $actions,
                'available_deductions' => $availableDeductions,
            ]);
        } catch (\Throwable $e) {
            Log::error("Invoice show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger les détails de la facture.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{id}/ancestor-chain",
     *     summary="Get invoice ancestor chain",
     *     description="Get the full ancestor and descendant chain for an invoice",
     *     operationId="getInvoiceAncestorChain",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Chain retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="ancestor_chain", type="array", @OA\Items()),
     *             @OA\Property(property="descendant_chain", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function ancestorChain(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $document = Document::where('company_id', $this->getCompanyId())
                ->where('documentable_type', Invoice::class)
                ->findOrFail($id);

            return response()->json([
                'ancestor_chain' => $document->getAncestorChain(),
                'descendant_chain' => $document->getDescendantChain(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Invoice ancestorChain error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/create",
     *     summary="Get invoice creation data",
     *     description="Get customers and other data required for creating an invoice",
     *     operationId="getInvoiceCreateData",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Creation data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="customers", type="array", @OA\Items(ref="#/components/schemas/Customer")),
     *             @OA\Property(property="tax_rates", type="array", @OA\Items()),
     *             @OA\Property(property="payment_conditions", type="array", @OA\Items()),
     *             @OA\Property(property="payment_modes", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function create(): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $data = $this->invoiceService->getCreationData();
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('Invoice create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors du chargement des données.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/invoices",
     *     summary="Create a new invoice",
     *     description="Create a new invoice draft",
     *     operationId="createInvoice",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"customer_id", "items"},
     *                 @OA\Property(property="customer_id", type="integer", example=1),
     *                 @OA\Property(property="type", type="string", enum={"STANDARD", "ACOMPTE"}, example="STANDARD"),
     *                 @OA\Property(property="date", type="string", format="date", example="2024-01-01"),
     *                 @OA\Property(property="due_date", type="string", format="date", nullable=true, example="2024-01-31"),
     *                 @OA\Property(property="payment_condition_id", type="integer", nullable=true),
     *                 @OA\Property(property="payment_mode_id", type="integer", nullable=true),
     *                 @OA\Property(property="reference", type="string", nullable=true, maxLength=100),
     *                 @OA\Property(property="notes", type="string", nullable=True),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="product_id", type="integer"),
     *                         @OA\Property(property="designation", type="string"),
     *                         @OA\Property(property="quantity", type="number", format="float"),
     *                         @OA\Property(property="unit_price", type="number", format="float"),
     *                         @OA\Property(property="tax_rate", type="number", format="float"),
     *                         @OA\Property(property="discount", type="number", format="float", nullable=true)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="201",
     *         description="Invoice created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Invoice")
     *     )
     * )
     */
    public function store(InvoiceRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $document = $this->invoiceService->createDraft($request->validated());
            return response()->json($document, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Invoice store error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la facture.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/invoices/{id}",
     *     summary="Update an invoice",
     *     description="Update an existing invoice",
     *     operationId="updateInvoice",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="customer_id", type="integer"),
     *                 @OA\Property(property="date", type="string", format="date"),
     *                 @OA\Property(property="due_date", type="string", format="date", nullable=true),
     *                 @OA\Property(property="payment_condition_id", type="integer", nullable=true),
     *                 @OA\Property(property="payment_mode_id", type="integer", nullable=true),
     *                 @OA\Property(property="reference", type="string", nullable=true),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="product_id", type="integer"),
     *                         @OA\Property(property="designation", type="string"),
     *                         @OA\Property(property="quantity", type="number", format="float"),
     *                         @OA\Property(property="unit_price", type="number", format="float"),
     *                         @OA\Property(property="tax_rate", type="number", format="float")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Invoice updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Invoice")
     *     )
     * )
     */
    public function update(InvoiceRequest $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $validated = $request->validated();
            $items = $validated['items'] ?? null;
            unset($validated['items']);

            $document = $this->invoiceService->updateMetadata($id, $validated);
            if ($items !== null) {
                $document = $this->invoiceService->updateItems($id, $items);
            }

            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Invoice update error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour de la facture.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/invoices/{id}/finalize",
     *     summary="Finalize an invoice",
     *     description="Finalize an invoice draft",
     *     operationId="finalizeInvoice",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Invoice finalized successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Invoice")
     *     ),
     *
     *     @OA\Response(
     *         response="422",
     *         description="Cannot finalize invoice",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function finalize(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $document = $this->invoiceService->finalize($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice finalize error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la finalisation.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/invoices/{id}/send",
     *     summary="Send an invoice",
     *     description="Send a finalized invoice to the customer",
     *     operationId="sendInvoice",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Invoice sent successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Invoice")
     *     )
     * )
     */
    public function send(int $id): JsonResponse
    {
        Gate::authorize('sign-document');
        try {
            $document = $this->invoiceService->send($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice send error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{id}/actions",
     *     summary="Get available invoice actions",
     *     description="Get available actions for an invoice based on its status",
     *     operationId="getInvoiceActions",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Actions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="actions", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function actions(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $actions = $this->invoiceService->getAvailableActions($id);
            return response()->json(['actions' => $actions]);
        } catch (\Throwable $e) {
            Log::error("Invoice actions error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/invoices/{id}/mark-overdue",
     *     summary="Mark invoice as overdue",
     *     description="Manually mark an invoice as overdue",
     *     operationId="markInvoiceOverdue",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Invoice marked as overdue",
     *         @OA\JsonContent(ref="#/components/schemas/Invoice")
     *     )
     * )
     */
    public function markOverdue(int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $document = $this->invoiceService->markOverdue($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice markOverdue error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du marquage.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/invoices/{id}/cancel",
     *     summary="Cancel an invoice",
     *     description="Cancel an invoice",
     *     operationId="cancelInvoice",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Invoice cancelled successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Invoice")
     *     )
     * )
     */
    public function cancel(int $id): JsonResponse
    {
        Gate::authorize('delete-document');
        try {
            $document = $this->invoiceService->cancel($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice cancel error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'annulation.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/invoices/{id}/add-deduction",
     *     summary="Add deposit deduction to invoice",
     *     description="Add a deposit as a deduction to an invoice",
     *     operationId="addInvoiceDeduction",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"deposit_id"},
     *                 @OA\Property(property="deposit_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="201",
     *         description="Deduction added successfully"
     *     )
     * )
     */
    public function addDeduction(Request $request, int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $depositId = $request->input('deposit_id');
            if (!$depositId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID d\'acompte requis.',
                ], 400);
            }

            $deduction = $this->invoiceService->addDeduction($id, $depositId);
            return response()->json($deduction, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice addDeduction error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'ajout de la déduction.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{id}/available-deductions",
     *     summary="Get available deposit deductions",
     *     description="Get available deposits that can be applied as deductions to an invoice",
     *     operationId="getAvailableDeductions",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Deductions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="deposits", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function availableDeductions(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $deposits = $this->invoiceService->getAvailableDeductions($id);
            return response()->json(['deposits' => $deposits]);
        } catch (\Throwable $e) {
            Log::error("Invoice availableDeductions error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/invoices/{id}/metadata",
     *     summary="Update invoice metadata",
     *     description="Update invoice metadata without modifying items",
     *     operationId="updateInvoiceMetadata",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="customer_id", type="integer"),
     *                 @OA\Property(property="date", type="string", format="date"),
     *                 @OA\Property(property="due_date", type="string", format="date", nullable=true),
     *                 @OA\Property(property="payment_condition_id", type="integer", nullable=true),
     *                 @OA\Property(property="payment_mode_id", type="integer", nullable=true),
     *                 @OA\Property(property="reference", type="string", nullable=true),
     *                 @OA\Property(property="notes", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Metadata updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Invoice")
     *     )
     * )
     */
    public function updateMetadata(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $document = $this->invoiceService->updateMetadata($id, $request->all());
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice updateMetadata error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/invoices/{id}/items",
     *     summary="Update invoice items",
     *     description="Update only the items of an invoice",
     *     operationId="updateInvoiceItems",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="product_id", type="integer"),
     *                         @OA\Property(property="designation", type="string"),
     *                         @OA\Property(property="quantity", type="number", format="float"),
     *                         @OA\Property(property="unit_price", type="number", format="float"),
     *                         @OA\Property(property="tax_rate", type="number", format="float")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Items updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Invoice")
     *     )
     * )
     */
    public function updateItems(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $document = $this->invoiceService->updateItems($id, $request->input('items', []));
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice updateItems error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/invoices/{id}/generate-credit-note",
     *     summary="Generate credit note from invoice",
     *     description="Generate a credit note from an invoice",
     *     operationId="generateCreditNote",
     *     tags={"Invoices"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="reason", type="string", example="Correction"),
     *                 @OA\Property(property="type", type="string", enum={"STANDARD", "DEPOSIT"}, example="STANDARD")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="201",
     *         description="Credit note generated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/CreditNote")
     *     )
     * )
     */
    public function generateCreditNote(Request $request, int $id): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $reason = $request->input('reason', 'Correction');
            $creditNoteType = $request->input('type', 'STANDARD');

            $sourceDocument = Document::where('company_id', $this->getCompanyId())
                ->where('documentable_type', Invoice::class)
                ->with(['documentable', 'items', 'customer'])
                ->findOrFail($id);

            $invoice = $sourceDocument->documentable;

            if (!in_array($invoice->status, ['FINALIZED', 'SENT', 'PAID', 'OVERDUE'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'La facture doit être finalisée, envoyée, payée ou en retard.',
                ], 422);
            }

            $isDepositInvoice = $invoice->type === 'ACOMPTE';

            if ($isDepositInvoice && $creditNoteType !== 'DEPOSIT') {
                return response()->json([
                    'success' => false,
                    'message' => 'Une facture d\'acompte ne peut générer qu\'un avoir d\'acompte.',
                ], 422);
            }

            if (!$isDepositInvoice && $creditNoteType === 'DEPOSIT') {
                return response()->json([
                    'success' => false,
                    'message' => 'Un avoir d\'acompte ne peut être créé qu\'à partir d\'une facture d\'acompte.',
                ], 422);
            }

            $items = [];
            foreach ($sourceDocument->items as $item) {
                $items[] = [
                    'product_id' => $item->product_id,
                    'designation' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                ];
            }

            $data = [
                'type' => $creditNoteType,
                'reason' => $reason,
                'items' => $items,
            ];

            $creditNoteService = app(\App\Services\CreditNoteService::class);
            $document = $creditNoteService->createFromInvoice($id, $data);

            return response()->json($document, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice generateCreditNote error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la génération de l\'avoir.',
            ], 500);
        }
    }
}
