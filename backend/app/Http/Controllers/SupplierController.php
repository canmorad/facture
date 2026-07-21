<?php

namespace App\Http\Controllers;

use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class SupplierController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/suppliers",
     *     summary="Get all suppliers",
     *     description="Get a list of all suppliers for the current company",
     *     operationId="getSuppliers",
     *     tags={"Suppliers"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Suppliers retrieved successfully",
     *         @OA\JsonContent(type="array", @OA\Items())
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $suppliers = $this->expenseService->getSuppliers();

            Log::info('SupplierController index', [
                'suppliers_count' => count($suppliers),
                'suppliers' => $suppliers,
            ]);

            return response()->json($suppliers);
        } catch (\Throwable $e) {
            Log::error('SupplierController index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Impossible de charger les fournisseurs.'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/suppliers",
     *     summary="Create a new supplier",
     *     operationId="createSupplier",
     *     tags={"Suppliers"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(property="name", type="string", maxLength=255),
     *                 @OA\Property(property="ice", type="string", maxLength=50, nullable=true),
     *                 @OA\Property(property="email", type="string", format="email", maxLength=255, nullable=true),
     *                 @OA\Property(property="phone", type="string", maxLength=50, nullable=true),
     *                 @OA\Property(property="address", type="string", maxLength=500, nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="201", description="Supplier created successfully")
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/api/suppliers/{id}",
     *     summary="Update a supplier",
     *     operationId="updateSupplier",
     *     tags={"Suppliers"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", maxLength=255),
     *                 @OA\Property(property="ice", type="string", maxLength=50, nullable=true),
     *                 @OA\Property(property="email", type="string", format="email", maxLength=255, nullable=true),
     *                 @OA\Property(property="phone", type="string", maxLength=50, nullable=true),
     *                 @OA\Property(property="address", type="string", maxLength=500, nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="200", description="Supplier updated successfully")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/suppliers/{id}",
     *     summary="Delete a supplier",
     *     operationId="deleteSupplier",
     *     tags={"Suppliers"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="Supplier deleted successfully")
     * )
     */
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
