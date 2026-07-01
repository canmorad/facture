<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Quote;
use App\Models\Document;

class QuoteController extends Controller
{
    public function __construct(protected QuoteService $quoteService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->quoteService->setCompanyId($companyId);
            $quotes = $this->quoteService->getAll($request->query('status'));
            return response()->json($quotes);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des devis.'], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $quote = Quote::with(['document.customer', 'document.items'])->find($id);
            if ($quote) {
                if ($quote->document->company_id != $companyId) {
                    return response()->json(['error' => 'Devis non autorisé.'], 403);
                }
                return response()->json($quote);
            }

            $document = Document::where('id', $id)
                ->where('documentable_type', Quote::class)
                ->first();

            if (!$document) {
                return response()->json(['error' => 'Devis introuvable.'], 404);
            }

            if ($document->company_id != $companyId) {
                return response()->json(['error' => 'Devis non autorisé.'], 403);
            }

            $quote = Quote::with(['document.customer', 'document.items'])
                ->find($document->documentable_id);

            if (!$quote) {
                return response()->json(['error' => 'Devis introuvable.'], 404);
            }

            return response()->json($quote);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Devis introuvable.'], 404);
        }
    }

    public function create(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->quoteService->setCompanyId($companyId);
            $data = $this->quoteService->getCreationData();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des données.'], 500);
        }
    }

    public function store(QuoteRequest $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->quoteService->setCompanyId($companyId);
            $document = $this->quoteService->createDraft($request->validated());
            return response()->json($document, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors de l\'enregistrement du devis.'], 500);
        }
    }

    public function finalize(Request $request, int $id): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->quoteService->setCompanyId($companyId);
            $document = $this->quoteService->finalize($id);
            return response()->json($document);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors de la finalisation du devis.'], 500);
        }
    }
}