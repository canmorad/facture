<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $expenses = $this->expenseService->getAll();
            return response()->json($expenses);
        } catch (\Throwable $e) {
            Log::error('ExpenseController index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Impossible de charger les dépenses.'], 500);
        }
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $files = $request->file('files', []);

            $expense = $this->expenseService->create($request->validated(), $files);

            return response()->json($expense, 201);
        } catch (\Throwable $e) {
            Log::error('ExpenseController store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erreur lors de la création de la dépense.'], 500);
        }
    }

    public function update(StoreExpenseRequest $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $files = $request->file('files', []);

            $expense = $this->expenseService->update($id, $request->validated(), $files);

            return response()->json($expense);
        } catch (\Throwable $e) {
            Log::error('ExpenseController update error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erreur lors de la modification de la dépense.'], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete-document');
        try {
            $this->expenseService->destroy($id);

            return response()->json(['message' => 'Dépense supprimée.']);
        } catch (\Throwable $e) {
            Log::error('ExpenseController destroy error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erreur lors de la suppression de la dépense.'], 500);
        }
    }

    public function toggleStatus(int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $expense = $this->expenseService->toggleStatus($id);

            return response()->json($expense);
        } catch (\Throwable $e) {
            Log::error('ExpenseController toggleStatus error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erreur lors du changement de statut.'], 500);
        }
    }

    public function getCreationData(): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $data = $this->expenseService->getCreationData();

            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('ExpenseController creation data error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Impossible de charger les données.'], 500);
        }
    }
}
