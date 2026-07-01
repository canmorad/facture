<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrderRequest;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $purchaseOrderService) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->purchaseOrderService->setCompanyId($companyId);
            $purchaseOrders = $this->purchaseOrderService->getAll($request->query('status'));
            return response()->json($purchaseOrders);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des bons de commande.'], 500);
        }
    }

    public function create(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->purchaseOrderService->setCompanyId($companyId);
            $data = $this->purchaseOrderService->getCreationData();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des données.'], 500);
        }
    }

    public function store(PurchaseOrderRequest $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->purchaseOrderService->setCompanyId($companyId);
            $document = $this->purchaseOrderService->createDraft($request->validated());
            return response()->json($document, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors de l\'enregistrement du bon de commande.'], 500);
        }
    }

    public function finalize(Request $request, int $id): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->purchaseOrderService->setCompanyId($companyId);
            $document = $this->purchaseOrderService->finalize($id);
            return response()->json($document);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors de la finalisation du bon de commande.'], 500);
        }
    }
}