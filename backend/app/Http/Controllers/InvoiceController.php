<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->invoiceService->setCompanyId($companyId);
            $invoices = $this->invoiceService->getAll($request->query('status'));
            return response()->json($invoices);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des factures.'], 500);
        }
    }

    public function create(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->invoiceService->setCompanyId($companyId);
            $data = $this->invoiceService->getCreationData();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des données.'], 500);
        }
    }

    public function store(InvoiceRequest $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->invoiceService->setCompanyId($companyId);
            $document = $this->invoiceService->createDraft($request->validated());
            return response()->json($document, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors de l\'enregistrement de la facture.'], 500);
        }
    }

    public function finalize(Request $request, int $id): JsonResponse
    {
        $companyId = $request->input('company_id');
        if (!$companyId) {
            return response()->json(['error' => 'Veuillez sélectionner une entreprise.'], 400);
        }

        try {
            $this->invoiceService->setCompanyId($companyId);
            $document = $this->invoiceService->finalize($id);
            return response()->json($document);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue lors de la finalisation de la facture.'], 500);
        }
    }
}