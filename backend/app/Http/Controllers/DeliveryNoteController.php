<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryNoteRequest;
use App\Services\DeliveryNoteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeliveryNoteController extends Controller
{
    public function __construct(protected DeliveryNoteService $deliveryNoteService) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->deliveryNoteService->setCompanyId($companyId);
            $deliveryNotes = $this->deliveryNoteService->getAll($request->query('status'));
            return response()->json($deliveryNotes);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des bons de livraison.'], 500);
        }
    }

    public function create(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->deliveryNoteService->setCompanyId($companyId);
            $data = $this->deliveryNoteService->getCreationData();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des données.'], 500);
        }
    }

    public function store(DeliveryNoteRequest $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->deliveryNoteService->setCompanyId($companyId);
            $document = $this->deliveryNoteService->createDraft($request->validated());
            return response()->json($document, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors de l\'enregistrement du bon de livraison.'], 500);
        }
    }

    public function finalize(Request $request, int $id): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->deliveryNoteService->setCompanyId($companyId);
            $document = $this->deliveryNoteService->finalize($id);
            return response()->json($document);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors de la finalisation du bon de livraison.'], 500);
        }
    }
}