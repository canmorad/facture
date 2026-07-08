<?php

namespace App\Http\Controllers;

use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class SupplierController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $suppliers = $this->expenseService->getSuppliers();

            return response()->json($suppliers);
        } catch (\Throwable $e) {
            Log::error('SupplierController index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Impossible de charger les fournisseurs.'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'ice' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'address' => 'nullable|string|max:500',
            ]);

            $supplier = $this->expenseService->createSupplier($validated);

            return response()->json($supplier, 201);
        } catch (\Throwable $e) {
            Log::error('SupplierController store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erreur lors de la création du fournisseur.'], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'ice' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'address' => 'nullable|string|max:500',
            ]);

            $supplier = $this->expenseService->updateSupplier($id, $validated);

            return response()->json($supplier);
        } catch (\Throwable $e) {
            Log::error('SupplierController update error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erreur lors de la mise à jour du fournisseur.'], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete-document');
        try {
            $this->expenseService->destroySupplier($id);

            return response()->json(['message' => 'Fournisseur supprimé.']);
        } catch (\Throwable $e) {
            Log::error('SupplierController destroy error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erreur lors de la suppression du fournisseur.'], 500);
        }
    }
}
