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

class QuoteController extends Controller
{
    public function __construct(protected QuoteService $quoteService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $quotes = $this->quoteService->getAll($request->query('status'));
            return response()->json($quotes);
        } catch (\Throwable $e) {
            Log::error('Quote index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des devis.',
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
                ->where('documentable_type', Quote::class)
                ->with(['customer', 'items', 'documentable', 'parent', 'children.documentable'])
                ->first();

            if (!$document) {
                $quote = Quote::with(['document.customer', 'document.items', 'document.parent', 'document.children.documentable'])->find($id);
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
            $actions = $this->quoteService->getAvailableActions($id);

            return response()->json([
                'document' => $document,
                'ancestor_chain' => $ancestorChain,
                'descendant_chain' => $descendantChain,
                'available_actions' => $actions,
            ]);
        } catch (\Throwable $e) {
            Log::error("Quote show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger les détails du devis.',
            ], 500);
        }
    }

    public function actions(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $actions = $this->quoteService->getAvailableActions($id);
            return response()->json(['actions' => $actions]);
        } catch (\Throwable $e) {
            Log::error("Quote actions error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
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

    public function sign(int $id): JsonResponse
    {
        Gate::authorize('sign-document');
        try {
            $document = $this->quoteService->sign($id);
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
}
