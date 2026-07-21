<?php

namespace App\Http\Controllers;

use App\Http\Requests\BalanceInvoiceRequest;
use App\Services\BalanceInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class BalanceInvoiceController extends Controller
{
    public function __construct(
        protected BalanceInvoiceService $balanceInvoiceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-documents');

        try {
            $filters = [
                'status' => $request->query('status'),
                'search' => $request->query('search'),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'customer_id' => $request->query('customer_id'),
            ];

            $perPage = (int) $request->query('per_page', 10);

            $result = $this->balanceInvoiceService->getPaginated($filters, $perPage);

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('BalanceInvoice index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des factures de solde.',
            ], 500);
        }
    }

    public function create(Request $request): JsonResponse
    {
        Gate::authorize('create-document');

        try {
            $quoteId = $request->query('quote_id') ? (int) $request->query('quote_id') : null;

            $data = $this->balanceInvoiceService->getCreationData($quoteId);

            return response()->json($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('BalanceInvoice create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors du chargement des données.',
            ], 500);
        }
    }

    public function getBalanceData(int $quoteId): JsonResponse
    {
        Gate::authorize('view-documents');

        try {
            $companyId = $this->getCompanyId();

            // Validate quote belongs to company
            $quote = \App\Models\Quote::with('document')
                ->whereHas('document', fn($q) => $q->where('company_id', $companyId))
                ->find($quoteId);

            if (!$quote) {
                return response()->json([
                    'success' => false,
                    'message' => 'Devis introuvable.',
                ], 404);
            }

            $data = $this->balanceInvoiceService->getBalanceData($quoteId);

            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error("BalanceInvoice getBalanceData error quote {$quoteId}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    public function store(BalanceInvoiceRequest $request): JsonResponse
    {
        Gate::authorize('create-document');

        try {
            $validated = $request->validated();

            $document = $this->balanceInvoiceService->createBalanceInvoice($validated);

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
            Log::error('BalanceInvoice store error: ' . $e->getMessage(), [
                'input' => $request->all(),
                'exception' => $e,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la facture de solde.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view-documents');

        try {
            $companyId = $this->getCompanyId();

            $document = \App\Models\Document::where('id', $id)
                ->where('company_id', $companyId)
                ->where('documentable_type', \App\Models\BalanceInvoice::class)
                ->with(['customer', 'items', 'documentable', 'parent'])
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facture de solde introuvable.',
                ], 404);
            }

            return response()->json([
                'document' => $document,
                'ancestor_chain' => $document->getAncestorChain(),
            ]);
        } catch (\Throwable $e) {
            Log::error("BalanceInvoice show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger les détails de la facture de solde.',
            ], 500);
        }
    }

    public function finalize(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');

        try {
            $document = $this->balanceInvoiceService->finalize($id);

            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("BalanceInvoice finalize error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la finalisation.',
            ], 500);
        }
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        Gate::authorize('update-document');

        $validated = $request->validate([
            'status' => 'required|in:DRAFT,FINALIZED,SENT,PAID,CANCELLED',
        ], [
            'status.required' => 'Le statut est requis.',
            'status.in' => 'Le statut doit être l\'une des valeurs suivantes : DRAFT, FINALIZED, SENT, PAID, CANCELLED.',
        ]);

        try {
            $companyId = $this->getCompanyId();

            // Verify document belongs to company
            $document = \App\Models\Document::where('id', $id)
                ->where('company_id', $companyId)
                ->where('documentable_type', \App\Models\BalanceInvoice::class)
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facture de solde introuvable.',
                ], 404);
            }

            $result = $this->balanceInvoiceService->updateStatus($id, $validated['status']);

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("BalanceInvoice updateStatus error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour du statut.',
            ], 500);
        }
    }
}
