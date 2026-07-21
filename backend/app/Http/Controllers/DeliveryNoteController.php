<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryNoteRequest;
use App\Services\DeliveryNoteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\DeliveryNote;
use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class DeliveryNoteController extends Controller
{
    public function __construct(protected DeliveryNoteService $deliveryNoteService) {}

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

            $deliveryNotes = $this->deliveryNoteService->getPaginated($filters, $perPage);
            return response()->json($deliveryNotes);
        } catch (\Throwable $e) {
            Log::error('DeliveryNote index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des données.',
            ], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $document = Document::where('id', $id)
                ->where('company_id', $this->getCompanyId())
                ->where('documentable_type', DeliveryNote::class)
                ->with(['customer', 'items', 'documentable', 'parent', 'children.documentable'])
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bon de livraison introuvable.',
                ], 404);
            }

            $actions = $this->deliveryNoteService->getAvailableActions($document->documentable_id);

            return response()->json([
                'document' => $document,
                'ancestor_chain' => $document->getAncestorChain(),
                'descendant_chain' => $document->getDescendantChain(),
                'available_actions' => $actions,
            ]);
        } catch (\Throwable $e) {
            Log::error("DeliveryNote show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger les détails du bon de livraison.',
            ], 500);
        }
    }

    public function actions(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $actions = $this->deliveryNoteService->getAvailableActions($id);
            return response()->json(['actions' => $actions]);
        } catch (\Throwable $e) {
            Log::error("DeliveryNote actions error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
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
            $data = $this->deliveryNoteService->getCreationData();
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('DeliveryNote create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors du chargement des données.',
            ], 500);
        }
    }

    public function store(DeliveryNoteRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $document = $this->deliveryNoteService->createDraft($request->validated());
            return response()->json($document, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DeliveryNote store error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du document.',
            ], 500);
        }
    }

    public function finalize(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $document = $this->deliveryNoteService->finalize($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("DeliveryNote finalize error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
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
            $document = $this->deliveryNoteService->send($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("DeliveryNote send error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi.',
            ], 500);
        }
    }

    public function deliver(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $document = $this->deliveryNoteService->markDelivered($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("DeliveryNote deliver error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la livraison.',
            ], 500);
        }
    }

    public function convertToInvoice(Request $request, int $id): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $invoiceType = $request->input('type', 'STANDARD');
            $document = $this->deliveryNoteService->convertToInvoice($id, $invoiceType);
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
            Log::error("DeliveryNote convertToInvoice error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la conversion.',
            ], 500);
        }
    }

    public function consolidateToInvoice(Request $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $deliveryNoteIds = $request->input('delivery_note_ids', []);
            $invoiceType = $request->input('type', 'STANDARD');

            if (count($deliveryNoteIds) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Au moins 2 bons de livraison sont requis.',
                ], 422);
            }

            $document = $this->deliveryNoteService->consolidateToInvoice($deliveryNoteIds, $invoiceType);
            return response()->json($document, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DeliveryNote consolidateToInvoice error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la consolidation.',
            ], 500);
        }
    }

    public function getConsolidatable(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $document = Document::where('id', $id)
                ->where('company_id', $this->getCompanyId())
                ->where('documentable_type', DeliveryNote::class)
                ->firstOrFail();

            $consolidatable = $this->deliveryNoteService->getConsolidatableDeliveryNotes($document->customer_id);

            return response()->json([
                'delivery_notes' => $consolidatable,
                'current_document_id' => $id,
            ]);
        } catch (\Throwable $e) {
            Log::error("DeliveryNote getConsolidatable error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
            ], 500);
        }
    }
}
