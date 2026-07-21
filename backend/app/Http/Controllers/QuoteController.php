<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Quote;
use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class QuoteController extends Controller
{
    public function __construct(protected QuoteService $quoteService) {}

    /**
     * @OA\Get(
     *     path="/api/quotes",
     *     summary="Get quotes with pagination",
     *     description="Get paginated list of quotes with optional filters",
     *     operationId="getQuotes",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         @OA\Schema(type="string", enum={"DRAFT", "FINALIZED", "SENT", "SIGNED", "EXPIRED", "ACCEPTED", "REFUSED"})
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
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Quotes retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Quote")
     *             ),
     *             @OA\Property(property="meta", type="object")
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
                'search' => $request->input('search'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'customer_id' => $request->input('customer_id'),
            ];

            $quotes = $this->quoteService->getPaginated($filters, $perPage);
            return response()->json($quotes);
        } catch (\Throwable $e) {
            Log::error('Quote index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des devis.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/quotes/{id}",
     *     summary="Get a quote by ID",
     *     description="Get a specific quote with all details",
     *     operationId="getQuote",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Quote retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="document", ref="#/components/schemas/Quote"),
     *             @OA\Property(property="ancestor_chain", type="array", @OA\Items()),
     *             @OA\Property(property="descendant_chain", type="array", @OA\Items()),
     *             @OA\Property(property="available_actions", type="array", @OA\Items())
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
                ->where('documentable_type', Quote::class)
                ->with(['customer.customerable', 'items', 'documentable', 'parent', 'children.documentable'])
                ->first();

            if (!$document) {
                $quote = Quote::with(['document.customer.customerable', 'document.items', 'document.parent', 'document.children.documentable'])->find($id);
                if ($quote && $quote->document && $quote->document->company_id == $companyId) {
                    $document = $quote->document;
                }
            }

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Devis introuvable.',
                ], 404);
            }

            $ancestorChain = $document->getAncestorChain();
            $descendantChain = $document->getDescendantChain();

            try {
                $actions = $this->quoteService->getAvailableActions($document->documentable_id);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Devis introuvable.',
                ], 404);
            }

            return response()->json([
                'document' => $document,
                'ancestor_chain' => $ancestorChain,
                'descendant_chain' => $descendantChain,
                'available_actions' => $actions,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Devis introuvable.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error("Quote show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger les détails du devis.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/quotes/{id}/actions",
     *     summary="Get available quote actions",
     *     operationId="getQuoteActions",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response="200",
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
            $actions = $this->quoteService->getAvailableActions($id);
            return response()->json(['actions' => $actions]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Devis introuvable.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error("Quote actions error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/quote/create",
     *     summary="Get quote creation data",
     *     operationId="getQuoteCreateData",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(response="200", description="Creation data retrieved successfully")
     * )
     */
    public function create(): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $data = $this->quoteService->getCreationData();
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('Quote create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors du chargement des données.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/quotes",
     *     summary="Create a new quote",
     *     operationId="createQuote",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"customer_id", "items"},
     *                 @OA\Property(property="customer_id", type="integer"),
     *                 @OA\Property(property="date", type="string", format="date"),
     *                 @OA\Property(property="valid_until", type="string", format="date", nullable=true),
     *                 @OA\Property(property="payment_condition_id", type="integer", nullable=true),
     *                 @OA\Property(property="payment_mode_id", type="integer", nullable=true),
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
     *     @OA\Response(response="201", description="Quote created successfully")
     * )
     */
    public function store(QuoteRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $document = $this->quoteService->createDraft($request->validated());
            return response()->json($document, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Quote store error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du devis.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/quotes/{id}",
     *     summary="Update a quote",
     *     operationId="updateQuote",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="customer_id", type="integer"),
     *                 @OA\Property(property="date", type="string", format="date"),
     *                 @OA\Property(property="valid_until", type="string", format="date", nullable=true),
     *                 @OA\Property(property="items", type="array", @OA\Items())
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="200", description="Quote updated successfully")
     * )
     */
    public function update(QuoteRequest $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $validated = $request->validated();
            $items = $validated['items'];
            unset($validated['items']);

            $document = $this->quoteService->updateMetadata($id, $validated);
            $document = $this->quoteService->updateItems($id, $items);

            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Quote update error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour du devis.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/quotes/{id}/finalize",
     *     summary="Finalize a quote",
     *     operationId="finalizeQuote",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Quote finalized successfully")
     * )
     */
    public function finalize(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $document = $this->quoteService->finalize($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Quote finalize error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la finalisation.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/quotes/{id}/send",
     *     summary="Send a quote",
     *     operationId="sendQuote",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Quote sent successfully")
     * )
     */
    public function send(int $id): JsonResponse
    {
        Gate::authorize('sign-document');
        try {
            $document = $this->quoteService->send($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Quote send error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/quotes/{id}/sign",
     *     summary="Sign a quote",
     *     operationId="signQuote",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="signed_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="200", description="Quote signed successfully")
     * )
     */
    public function sign(Request $request, int $id): JsonResponse
    {
        Gate::authorize('sign-document');
        try {
            $signedAt = $request->input('signed_at');
            $document = $this->quoteService->sign($id, $signedAt);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Quote sign error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la signature.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/quotes/{id}/convert-to-invoice",
     *     summary="Convert quote to invoice",
     *     operationId="convertQuoteToInvoice",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="type", type="string", enum={"STANDARD", "ACOMPTE"}, default="STANDARD")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="200", description="Quote converted successfully")
     * )
     */
    public function convertToInvoice(Request $request, int $id): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $invoiceType = $request->input('type', 'STANDARD');
            $document = $this->quoteService->convertToInvoice($id, $invoiceType);
            return response()->json($document);
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
            Log::error("Quote convertToInvoice error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la conversion.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/quotes/{id}/convert-to-purchase-order",
     *     summary="Convert quote to purchase order",
     *     operationId="convertQuoteToPurchaseOrder",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Quote converted successfully")
     * )
     */
    public function convertToPurchaseOrder(int $id): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $document = $this->quoteService->convertToPurchaseOrder($id);
            return response()->json($document);
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
            Log::error("Quote convertToPurchaseOrder error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la conversion.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/quotes/{id}/convert-to-proforma",
     *     summary="Convert quote to proforma",
     *     operationId="convertQuoteToProforma",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="201", description="Quote converted successfully")
     * )
     */
    public function convertToProforma(int $id): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $document = $this->quoteService->convertToProforma($id);
            return response()->json($document, 201);
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
            Log::error("Quote convertToProforma error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la conversion.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/quotes/{id}/create-delivery-note",
     *     summary="Create delivery note from quote",
     *     operationId="createDeliveryNoteFromQuote",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="201", description="Delivery note created successfully")
     * )
     */
    public function createDeliveryNote(int $id): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $document = $this->quoteService->createDeliveryNoteFromQuote($id);
            return response()->json($document, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Quote createDeliveryNote error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du bon de livraison.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/quotes/{id}/metadata",
     *     summary="Update quote metadata",
     *     operationId="updateQuoteMetadata",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Metadata updated successfully")
     * )
     */
    public function updateMetadata(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $document = $this->quoteService->updateMetadata($id, $request->all());
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Quote updateMetadata error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/quotes/{id}/items",
     *     summary="Update quote items",
     *     operationId="updateQuoteItems",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Items updated successfully")
     * )
     */
    public function updateItems(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $document = $this->quoteService->updateItems($id, $request->input('items', []));
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Quote updateItems error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/quotes/{id}/workflow",
     *     summary="Get quote workflow information",
     *     operationId="getQuoteWorkflowInfo",
     *     tags={"Quotes"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Workflow info retrieved successfully")
     * )
     */
    public function getWorkflowInfo(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $document = Document::where('id', $id)
                ->where('company_id', $this->getCompanyId())
                ->where('documentable_type', Quote::class)
                ->firstOrFail();

            $lineage = $document->getWorkflowSummary();

            return response()->json([
                'document' => $document->load(['customer.customerable', 'documentable']),
                'lineage' => $lineage,
            ]);
        } catch (\Throwable $e) {
            Log::error("Quote getWorkflowInfo error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
            ], 500);
        }
    }
}
