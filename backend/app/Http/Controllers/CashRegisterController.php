<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashRegisterRequest;
use App\Http\Requests\CashRegisterSessionRequest;
use App\Http\Requests\CashTransactionRequest;
use App\Services\CashRegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class CashRegisterController extends Controller
{
    public function __construct(protected CashRegisterService $cashRegisterService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $perPage = (int) $request->input('per_page', 10);
            $filters = [
                'search' => $request->input('search'),
                'type' => $request->input('type'),
                'is_active' => $request->input('is_active'),
            ];

            $result = $this->cashRegisterService->getPaginated($filters, $perPage);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('CashRegister index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des caisses.',
            ], 500);
        }
    }

    public function getAll(): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $result = $this->cashRegisterService->getAll();
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('CashRegister getAll error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $cashRegister = $this->cashRegisterService->findById($id);
            return response()->json($cashRegister);
        } catch (\Throwable $e) {
            Log::error("CashRegister show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Caisse introuvable.',
            ], 404);
        }
    }

    public function dashboard(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $data = $this->cashRegisterService->getDashboardData($id);
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error("CashRegister dashboard error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger les données du tableau de bord.',
            ], 500);
        }
    }

    public function create(): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $data = $this->cashRegisterService->getCreationData();
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('CashRegister create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors du chargement des données.',
            ], 500);
        }
    }

    public function store(CashRegisterRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $cashRegister = $this->cashRegisterService->create($request->validated());
            return response()->json($cashRegister, 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('CashRegister store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la caisse.',
            ], 500);
        }
    }

    public function update(int $id, CashRegisterRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $cashRegister = $this->cashRegisterService->update($id, $request->validated());
            return response()->json($cashRegister);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("CashRegister update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $this->cashRegisterService->delete($id);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("CashRegister destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression.',
            ], 500);
        }
    }

    public function toggleStatus(int $id): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $cashRegister = $this->cashRegisterService->toggleStatus($id);
            return response()->json($cashRegister);
        } catch (\Throwable $e) {
            Log::error("CashRegister toggleStatus error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
            ], 500);
        }
    }

    public function setDefault(int $id): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $cashRegister = $this->cashRegisterService->setDefault($id);
            return response()->json($cashRegister);
        } catch (\Throwable $e) {
            Log::error("CashRegister setDefault error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
            ], 500);
        }
    }

    public function openSession(int $id, CashRegisterSessionRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $session = $this->cashRegisterService->openSession($id, $request->validated());
            return response()->json($session, 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("CashRegister openSession error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'ouverture de session.',
            ], 500);
        }
    }

    public function closeSession(int $id, CashRegisterSessionRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $session = $this->cashRegisterService->closeSession($id, $request->validated());
            return response()->json($session);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("CashRegister closeSession error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la clôture de session.',
            ], 500);
        }
    }

    public function transactions(Request $request): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $perPage = (int) $request->input('per_page', 15);
            $filters = [
                'cash_register_id' => $request->input('cash_register_id'),
                'session_id' => $request->input('session_id'),
                'type' => $request->input('type'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ];

            $result = $this->cashRegisterService->getTransactions($filters, $perPage);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('CashRegister transactions error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des transactions.',
            ], 500);
        }
    }

    public function storeTransaction(CashTransactionRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $transaction = $this->cashRegisterService->createTransaction($request->validated());
            return response()->json($transaction, 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('CashRegister storeTransaction error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la transaction.',
            ], 500);
        }
    }

    public function updateTransaction(int $id, CashTransactionRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $transaction = $this->cashRegisterService->updateTransaction($id, $request->validated());
            return response()->json($transaction);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("CashRegister updateTransaction error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour de la transaction.',
            ], 500);
        }
    }

    public function deleteTransaction(int $id): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $this->cashRegisterService->deleteTransaction($id);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("CashRegister deleteTransaction error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression de la transaction.',
            ], 500);
        }
    }

    public function getSessions(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $companyId = $this->getCompanyId();
            $sessions = \App\Models\CashRegisterSession::where('company_id', $companyId)
                ->where('cash_register_id', $id)
                ->with(['openedBy', 'closedBy'])
                ->orderBy('opened_at', 'desc')
                ->paginate(20);

            return response()->json($sessions);
        } catch (\Throwable $e) {
            Log::error("CashRegister getSessions error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
            ], 500);
        }
    }
}
