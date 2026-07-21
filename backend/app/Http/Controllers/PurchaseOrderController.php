<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrderRequest;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PurchaseOrder;
use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $purchaseOrderService) {}

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

            $purchaseOrders = $this->purchaseOrderService->getPaginated($filters, $perPage);
            return response()->json($purchaseOrders);
        } catch (\Throwable $e) {
            Log::error('PurchaseOrder index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des commandes.',
            ], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $companyId = $this->getCompanyId();
            $document = Document::where('id', $id)
                ->where('company_id', $companyId)
                ->where('documentable_type', PurchaseOrder::class)
                ->with(['customer', 'items', 'documentable', 'parent'])
                ->first();

            if (!$document) {
                $po = PurchaseOrder::with(['document.customer', 'document.items', 'document.parent'])->find($id);
                if ($po && $po->document && $po->document->company_id == $companyId) {
                    $document = $po->document;
                }
            }

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commande introuvable.',
                ], 404);
            }

            return response()->json([
                'document' => $document,
                'is_derived_from_quote' => $document->isDerivedFromQuote(),
                'ancestor_chain' => $document->getAncestorChain(),
            ]);
        } catch (\Throwable $e) {
            Log::error("PurchaseOrder show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger les détails de la commande.',
            ], 500);
        }
    }

    public function ancestorChain(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $document = Document::where('company_id', $this->getCompanyId())
                ->where('documentable_type', PurchaseOrder::class)
                ->findOrFail($id);

            return response()->json([
                'ancestor_chain' => $document->getAncestorChain(),
            ]);
        } catch (\Throwable $e) {
            Log::error("PurchaseOrder ancestorChain error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    public function create(): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $data = $this->purchaseOrderService->getCreationData();
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('PurchaseOrder create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors du chargement des données.',
            ], 500);
        }
    }

    public function store(PurchaseOrderRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $document = $this->purchaseOrderService->createDraft($request->validated());
            return response()->json($document, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('PurchaseOrder store error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la commande.',
            ], 500);
        }
    }

    public function finalize(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $document = $this->purchaseOrderService->finalize($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("PurchaseOrder finalize error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la finalisation.',
            ], 500);
        }
    }

    public function send(int $id): JsonResponse
    {
        Gate::authorize('sign-document');
        try {
            $companyId = $this->getCompanyId();
            $document = Document::with('documentable')
                ->where('company_id', $companyId)
                ->where('documentable_type', PurchaseOrder::class)
                ->findOrFail($id);

            $document->documentable->transitionTo('SENT');

            return response()->json($document->fresh(['customer', 'items', 'documentable', 'parent']));
        } catch (\Throwable $e) {
            Log::error("PurchaseOrder send error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi.',
            ], 500);
        }
    }

    public function confirm(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $companyId = $this->getCompanyId();
            $document = Document::with('documentable')
                ->where('company_id', $companyId)
                ->where('documentable_type', PurchaseOrder::class)
                ->findOrFail($id);

            $document->documentable->transitionTo('CONFIRMED');

            return response()->json($document->fresh(['customer', 'items', 'documentable', 'parent']));
        } catch (\Throwable $e) {
            Log::error("PurchaseOrder confirm error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la confirmation.',
            ], 500);
        }
    }

    public function updateMetadata(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $document = $this->purchaseOrderService->updateMetadata($id, $request->all());
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("PurchaseOrder updateMetadata error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }

    public function updateItems(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $document = $this->purchaseOrderService->updateItems($id, $request->input('items', []));
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("PurchaseOrder updateItems error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }
}
