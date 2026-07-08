<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepositRequest;
use App\Services\DepositService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Document;
use App\Models\Quote;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class DepositController extends Controller
{
    public function __construct(protected DepositService $depositService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $deposits = $this->depositService->getAll($request->query('status'));
            return response()->json($deposits);
        } catch (\Throwable $e) {
            Log::error('Deposit index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des acomptes.',
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
                ->with(['customer', 'items', 'documentable', 'parent'])
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acompte introuvable.',
                ], 404);
            }

            return response()->json([
                'document' => $document,
                'ancestor_chain' => $document->getAncestorChain(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Deposit show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger les détails de l\'acompte.',
            ], 500);
        }
    }

    public function create(): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $data = $this->depositService->getCreationData();
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('Deposit create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors du chargement des données.',
            ], 500);
        }
    }

    private function getQuoteIdFromDocumentId(int $id, int $companyId): ?int
    {
        $quote = Quote::where('id', $id)->first();

        if ($quote) {
            $document = Document::where('documentable_type', Quote::class)
                ->where('documentable_id', $quote->id)
                ->where('company_id', $companyId)
                ->first();

            if ($document) {
                return $quote->id;
            }
        }

        $document = Document::where('id', $id)
            ->where('documentable_type', Quote::class)
            ->where('company_id', $companyId)
            ->first();

        if ($document) {
            return $document->documentable_id;
        }

        return null;
    }

    public function remainingBalance(Request $request, int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $companyId = $this->getCompanyId();
            $quoteId = $this->getQuoteIdFromDocumentId($id, $companyId);
            if (!$quoteId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Devis introuvable.',
                ], 404);
            }

            $balance = $this->depositService->getRemainingBalance($quoteId);
            return response()->json($balance);
        } catch (\Throwable $e) {
            Log::error("Deposit remainingBalance error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    public function store(DepositRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $companyId = $this->getCompanyId();
            $payload = $request->validated();
            $quoteId = $this->getQuoteIdFromDocumentId($payload['quote_id'], $companyId);
            if (!$quoteId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Devis source introuvable.',
                ], 404);
            }
            $payload['quote_id'] = $quoteId;

            $document = $this->depositService->createDeposit($payload);
            return response()->json($document, 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Deposit store error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de l\'acompte.',
            ], 500);
        }
    }

    public function finalize(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $document = $this->depositService->finalize($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Deposit finalize error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la finalisation.',
            ], 500);
        }
    }

    public function markPaid(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $companyId = $this->getCompanyId();
            $document = Document::where('company_id', $companyId)
                ->where('documentable_type', \App\Models\Deposit::class)
                ->findOrFail($id);

            $document->documentable->transitionTo('PAID');

            return response()->json($document->fresh(['customer', 'items', 'documentable', 'parent']));
        } catch (\Throwable $e) {
            Log::error("Deposit markPaid error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du paiement.',
            ], 500);
        }
    }
}
