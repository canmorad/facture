<?php

namespace App\Http\Controllers;

use App\Services\BankRemittanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class BankRemittanceController extends Controller
{
    public function __construct(protected BankRemittanceService $remittanceService) {}

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
                'bank_account_id' => $request->input('bank_account_id'),
            ];

            $remittances = $this->remittanceService->getPaginated($filters, $perPage);
            return response()->json($remittances);
        } catch (\Throwable $e) {
            Log::error('BankRemittance index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des remises.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $remittance = $this->remittanceService->show($id);
            return response()->json($remittance);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Remise introuvable.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error("BankRemittance show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
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
            $data = $this->remittanceService->getCreationData();
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('BankRemittance create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors du chargement des données.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $validated = $request->validate([
                'bank_account_id' => 'required|exists:bank_accounts,id',
                'remittance_date' => 'required|date',
                'payment_document_ids' => 'array',
                'payment_document_ids.*' => 'exists:payment_documents,id',
                'notes' => 'nullable|string',
            ]);

            $remittance = $this->remittanceService->createDraft($validated);
            return response()->json($remittance, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('BankRemittance store error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la remise.',
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $validated = $request->validate([
                'bank_account_id' => 'required|exists:bank_accounts,id',
                'remittance_date' => 'required|date',
                'payment_document_ids' => 'array',
                'payment_document_ids.*' => 'exists:payment_documents,id',
                'notes' => 'nullable|string',
            ]);

            $remittance = $this->remittanceService->update($id, $validated);
            return response()->json($remittance);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Remise introuvable.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error("BankRemittance update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }

    public function finalize(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $remittance = $this->remittanceService->finalize($id);
            return response()->json($remittance);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("BankRemittance finalize error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
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
            $remittance = $this->remittanceService->send($id);
            return response()->json($remittance);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("BankRemittance send error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi.',
            ], 500);
        }
    }

    public function markDeposited(Request $request, int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $depositSlipRef = $request->input('deposit_slip_reference');
            $remittance = $this->remittanceService->markDeposited($id, $depositSlipRef);
            return response()->json($remittance);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("BankRemittance markDeposited error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
            ], 500);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        Gate::authorize('delete-document');
        try {
            $remittance = $this->remittanceService->cancel($id);
            return response()->json($remittance);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("BankRemittance cancel error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'annulation.',
            ], 500);
        }
    }

    public function actions(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $actions = $this->remittanceService->getAvailableActions($id);
            return response()->json(['actions' => $actions]);
        } catch (\Throwable $e) {
            Log::error("BankRemittance actions error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    public function removeDocument(int $id, int $documentId): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $remittance = $this->remittanceService->removePaymentDocument($id, $documentId);
            return response()->json($remittance);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("BankRemittance removeDocument error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression du document.',
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete-document');
        try {
            $this->remittanceService->delete($id);
            return response()->json([
                'success' => true,
                'message' => 'Remise supprimée avec succès.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("BankRemittance delete error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression.',
            ], 500);
        }
    }

    public function pendingDocuments(): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $documents = $this->remittanceService->getPendingPaymentDocuments();
            return response()->json($documents);
        } catch (\Throwable $e) {
            Log::error('BankRemittance pendingDocuments error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }
}
