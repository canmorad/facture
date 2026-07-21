<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

class ProductCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/product-categories",
     *     summary="Get all product categories",
     *     description="Get a list of all product categories for the current company",
     *     operationId="getProductCategories",
     *     tags={"Products"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Categories retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/ProductCategory")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $companyId = $this->getCompanyId();
            $categories = ProductCategory::where('company_id', $companyId)->get();
            return response()->json($categories);
        } catch (\Throwable $e) {
            Log::error('ProductCategory index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/product-categories",
     *     summary="Create a new product category",
     *     description="Create a new product category for the current company",
     *     operationId="createProductCategory",
     *     tags={"Products"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(property="name", type="string", maxLength=255, example="Electronics"),
     *                 @OA\Property(property="description", type="string", maxLength=255, nullable=true, example="Electronic products"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_default", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="201",
     *         description="Category created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ProductCategory")
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $companyId = $this->getCompanyId();
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'is_default' => 'boolean',
            ]);

            $data['company_id'] = $companyId;

            $category = ProductCategory::create($data);
            return response()->json($category, 201);
        } catch (\Throwable $e) {
            Log::error('ProductCategory store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la création.'], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/product-categories/{id}",
     *     summary="Update a product category",
     *     description="Update a specific product category by ID",
     *     operationId="updateProductCategory",
     *     tags={"Products"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", maxLength=255),
     *                 @OA\Property(property="description", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="is_default", type="boolean")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Category updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ProductCategory")
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $data = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'is_default' => 'boolean',
            ]);

            $category = ProductCategory::where('company_id', $companyId)->findOrFail($id);
            $category->update($data);
            return response()->json($category);
        } catch (\Throwable $e) {
            Log::error("ProductCategory update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.'], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/product-categories/{id}",
     *     summary="Delete a product category",
     *     description="Delete a specific product category by ID",
     *     operationId="deleteProductCategory",
     *     tags={"Products"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="204",
     *         description="Category deleted successfully"
     *     ),
     *
     *     @OA\Response(
     *         response="422",
     *         description="Cannot delete category with products",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Impossible de supprimer cette catégorie car elle est liée à un ou plusieurs produits.")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $companyId = $this->getCompanyId();
            $category = ProductCategory::where('company_id', $companyId)->findOrFail($id);
            $category->delete();
            return response()->json(null, 204);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['success' => false, 'message' => 'Impossible de supprimer cette catégorie car elle est liée à un ou plusieurs produits.'], 422);
        } catch (\Throwable $e) {
            Log::error("ProductCategory destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'], 500);
        }
    }
}
