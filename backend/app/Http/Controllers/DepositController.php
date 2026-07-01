<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepositRequest;
use App\Services\DepositService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Document;
use App\Models\Quote;

class DepositController extends Controller
{
    public function __construct(protected DepositService $depositService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->depositService->setCompanyId($companyId);
            $deposits = $this->depositService->getAll($request->query('status'));
            return response()->json($deposits);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des acomptes.'], 500);
        }
    }

    public function create(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->depositService->setCompanyId($companyId);
            $data = $this->depositService->getCreationData();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $quoteId = $this->getQuoteIdFromDocumentId($id, $companyId);
            if (!$quoteId) {
                return response()->json(['error' => 'Devis introuvable.'], 404);
            }

            $this->depositService->setCompanyId($companyId);
            $balance = $this->depositService->getRemainingBalance($quoteId);
            return response()->json($balance);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

public function store(DepositRequest $request): JsonResponse
{
    $companyId = $request->input('company_id');
    if (!$companyId) {
        return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
    }

    $payload = $request->validated();
    $quoteId = $this->getQuoteIdFromDocumentId($payload['quote_id'], $companyId);
    if (!$quoteId) {
        return response()->json(['error' => 'Devis source introuvable.'], 404);
    }
    $payload['quote_id'] = $quoteId;

    try {
        $this->depositService->setCompanyId($companyId);
        $document = $this->depositService->createDeposit($payload);
        return response()->json($document, 201);
    } catch (\App\Exceptions\DepositLimitExceededException $e) {
        return $e->render();
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    public function finalize(Request $request, int $id): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->depositService->setCompanyId($companyId);
            $document = $this->depositService->finalize($id);
            return response()->json($document);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors de la finalisation de l\'acompte.'], 500);
        }
    }
}