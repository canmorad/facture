<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/expenses",
     *     summary="Get all expenses",
     *     operationId="getExpenses",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(response="200", description="Expenses retrieved successfully")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/expenses",
     *     summary="Create a new expense",
     *     operationId="createExpense",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"date", "amount", "category"},
     *                 @OA\Property(property="date", type="string", format="date"),
     *                 @OA\Property(property="amount", type="number", format="float"),
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="supplier_id", type="integer", nullable=true),
     *                 @OA\Property(property="files", type="array", @OA\Items(type="string", format="binary"), nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="201", description="Expense created successfully")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/expenses/{id}",
     *     summary="Update an expense",
     *     operationId="updateExpense",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="date", type="string", format="date"),
     *                 @OA\Property(property="amount", type="number", format="float"),
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="supplier_id", type="integer", nullable=true),
     *                 @OA\Property(property="files", type="array", @OA\Items(type="string", format="binary"), nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="200", description="Expense updated successfully")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/expenses/{id}",
     *     summary="Delete an expense",
     *     operationId="deleteExpense",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Expense deleted successfully")
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/expenses/{id}/toggle-status",
     *     summary="Toggle expense status",
     *     operationId="toggleExpenseStatus",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Status toggled successfully")
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/expenses/create",
     *     summary="Get expense creation data",
     *     operationId="getExpenseCreationData",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(response="200", description="Creation data retrieved successfully")
     * )
     */
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
