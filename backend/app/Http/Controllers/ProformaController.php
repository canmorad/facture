<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProformaRequest;
use App\Services\ProformaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Proforma;
use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class ProformaController extends Controller
{
    public function __construct(protected ProformaService $proformaService) {}

    /**
     * @OA\Get(
     *     path="/api/proformas",
     *     summary="Get proformas with pagination",
     *     operationId="getProformas",
     *     tags={"Proformas"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="customer_id", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Proformas retrieved successfully")
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

            $proformas = $this->proformaService->getPaginated($filters, $perPage);
            return response()->json($proformas);
        } catch (\Throwable $e) {
            Log::error('Proforma index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des factures proforma.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/proformas/{id}",
     *     summary="Get a proforma by ID",
     *     operationId="getProforma",
     *     tags={"Proformas"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Proforma retrieved successfully")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $companyId = $this->getCompanyId();
            $document = Document::where('id', $id)
                ->where('company_id', $companyId)
                ->where('documentable_type', Proforma::class)
                ->with(['customer', 'items', 'documentable', 'parent', 'parent.documentable', 'children.documentable'])
                ->first();

            if (!$document) {
                $proforma = Proforma::with(['document.customer', 'document.items', 'document.parent', 'document.children.documentable'])->find($id);
                if ($proforma && $proforma->document && $proforma->document->company_id == $companyId) {
                    $document = $proforma->document;
                }
            }

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facture proforma introuvable.',
                ], 404);
            }

            $ancestorChain = $document->getAncestorChain();
            $descendantChain = $document->getDescendantChain();
            $actions = $this->proformaService->getAvailableActions($document->documentable_id);

            return response()->json([
                'document' => $document,
                'is_derived_from_quote' => $document->isDerivedFromQuote(),
                'ancestor_chain' => $ancestorChain,
                'descendant_chain' => $descendantChain,
                'available_actions' => $actions,
            ]);
        } catch (\Throwable $e) {
            Log::error("Proforma show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger les détails de la facture proforma.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/proformas/{id}/ancestor-chain",
     *     summary="Get proforma ancestor chain",
     *     operationId="getProformaAncestorChain",
     *     tags={"Proformas"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Chain retrieved successfully")
     * )
     */
    public function ancestorChain(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $document = Document::where('company_id', $this->getCompanyId())
                ->where('documentable_type', Proforma::class)
                ->findOrFail($id);

            return response()->json([
                'ancestor_chain' => $document->getAncestorChain(),
                'descendant_chain' => $document->getDescendantChain(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Proforma ancestorChain error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/proformas/create",
     *     summary="Get proforma creation data",
     *     operationId="getProformaCreateData",
     *     tags={"Proformas"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="parent_document_id", in="query", @OA\Schema(type="integer", nullable=true)),
     *
     *     @OA\Response(response="200", description="Creation data retrieved successfully")
     * )
     */
    public function create(Request $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $parentDocumentId = $request->input('parent_document_id');
            $data = $this->proformaService->getCreationData($parentDocumentId);
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('Proforma create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors du chargement des données.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/proformas",
     *     summary="Create a new proforma",
     *     operationId="createProforma",
     *     tags={"Proformas"},
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
     *                 @OA\Property(property="parent_document_id", type="integer", nullable=true),
     *                 @OA\Property(property="items", type="array", @OA\Items())
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="201", description="Proforma created successfully")
     * )
     */
    public function store(ProformaRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $document = $this->proformaService->createDraft($request->validated());
            return response()->json($document, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Proforma store error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la facture proforma.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/proformas/{id}/finalize",
     *     summary="Finalize a proforma",
     *     operationId="finalizeProforma",
     *     tags={"Proformas"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Proforma finalized successfully")
     * )
     */
    public function finalize(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $document = $this->proformaService->finalize($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Proforma finalize error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la finalisation.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/proformas/{id}/send",
     *     summary="Send a proforma",
     *     operationId="sendProforma",
     *     tags={"Proformas"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Proforma sent successfully")
     * )
     */
    public function send(int $id): JsonResponse
    {
        Gate::authorize('sign-document');
        try {
            $document = $this->proformaService->send($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Proforma send error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/proformas/{id}/actions",
     *     summary="Get available proforma actions",
     *     operationId="getProformaActions",
     *     tags={"Proformas"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Actions retrieved successfully")
     * )
     */
    public function actions(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $actions = $this->proformaService->getAvailableActions($id);
            return response()->json(['actions' => $actions]);
        } catch (\Throwable $e) {
            Log::error("Proforma actions error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/proformas/{id}/mark-expired",
     *     summary="Mark proforma as expired",
     *     operationId="markProformaExpired",
     *     tags={"Proformas"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Proforma marked as expired")
     * )
     */
    public function markExpired(int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $document = $this->proformaService->markExpired($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Proforma markExpired error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du marquage.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/proformas/{id}/cancel",
     *     summary="Cancel a proforma",
     *     operationId="cancelProforma",
     *     tags={"Proformas"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Proforma cancelled successfully")
     * )
     */
    public function cancel(int $id): JsonResponse
    {
        Gate::authorize('delete-document');
        try {
            $document = $this->proformaService->cancel($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Proforma cancel error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'annulation.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/proformas/{id}/convert-to-invoice",
     *     summary="Convert proforma to invoice",
     *     operationId="convertProformaToInvoice",
     *     tags={"Proformas"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="201", description="Proforma converted successfully")
     * )
     */
    public function convertToInvoice(int $id): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $invoiceDocument = $this->proformaService->convertToInvoice($id);
            return response()->json($invoiceDocument, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Proforma convertToInvoice error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la conversion.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/proformas/{id}/metadata",
     *     summary="Update proforma metadata",
     *     operationId="updateProformaMetadata",
     *     tags={"Proformas"},
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
            $document = $this->proformaService->updateMetadata($id, $request->all());
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Proforma updateMetadata error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/proformas/{id}/items",
     *     summary="Update proforma items",
     *     operationId="updateProformaItems",
     *     tags={"Proformas"},
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
            $document = $this->proformaService->updateItems($id, $request->input('items', []));
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Proforma updateItems error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }
}
