<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\TaxRate;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/products/create",
     *     summary="Get product creation data",
     *     description="Get tax rates and categories for creating a product",
     *     operationId="getProductCreateData",
     *     tags={"Products"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Creation data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="tax_rates",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/TaxRate")
     *             ),
     *             @OA\Property(
     *                 property="categories",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/ProductCategory")
     *             )
     *         )
     *     )
     * )
     */
    public function create()
    {
        Gate::authorize('create-product');
        try {
            $companyId = $this->getCompanyId();
            $taxRates = TaxRate::where('company_id', $companyId)->get();
            $categories = ProductCategory::where('company_id', $companyId)->get();

            return response()->json([
                'tax_rates' => $taxRates,
                'categories' => $categories,
            ]);
        } catch (\Throwable $e) {
            Log::error('Product create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/products",
     *     summary="Get all products",
     *     description="Get a list of all products for the current company",
     *     operationId="getProducts",
     *     tags={"Products"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Product")
     *         )
     *     )
     * )
     */
    public function index()
    {
        Gate::authorize('view-products');
        try {
            $companyId = $this->getCompanyId();
            $products = Product::where('company_id', $companyId)
                ->with(['category', 'taxRate'])
                ->latest()
                ->get();

            return response()->json($products);
        } catch (\Throwable $e) {
            Log::error('Product index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/products",
     *     summary="Create a new product",
     *     description="Create a new product for the current company",
     *     operationId="createProduct",
     *     tags={"Products"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name", "price"},
     *                 @OA\Property(property="name", type="string", maxLength=255, example="Product Name"),
     *                 @OA\Property(property="price", type="number", format="float", minimum=0, example=99.99),
     *                 @OA\Property(property="description", type="string", maxLength=1000, nullable=true, example="Product description"),
     *                 @OA\Property(property="category_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="tax_rate_id", type="integer", nullable=true, example=1)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="201",
     *         description="Product created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     )
     * )
     */
    public function store(Request $request)
    {
        Gate::authorize('create-product');
        try {
            $companyId = $this->getCompanyId();
            $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'description' => 'nullable|string|max:1000',
                'category_id' => 'nullable|exists:product_categories,id',
                'tax_rate_id' => 'nullable|exists:tax_rates,id',
            ]);

            $product = Product::create([
                'company_id' => $companyId,
                'name' => $request->name,
                'price' => $request->price,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'tax_rate_id' => $request->tax_rate_id,
            ]);

            return response()->json($product, 201);
        } catch (\Throwable $e) {
            Log::error('Product store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la création.'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     summary="Get a product by ID",
     *     description="Get a specific product by ID",
     *     operationId="getProduct",
     *     tags={"Products"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *
     *     @OA\Response(
     *         response="404",
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Produit introuvable.")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        Gate::authorize('view-products');
        try {
            $companyId = $this->getCompanyId();
            $product = Product::where('company_id', $companyId)->findOrFail($id);
            return response()->json($product);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Produit introuvable.'], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/products/{id}",
     *     summary="Update a product",
     *     description="Update a specific product by ID",
     *     operationId="updateProduct",
     *     tags={"Products"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name", "price"},
     *                 @OA\Property(property="name", type="string", maxLength=255),
     *                 @OA\Property(property="price", type="number", format="float", minimum=0),
     *                 @OA\Property(property="description", type="string", maxLength=1000, nullable=true),
     *                 @OA\Property(property="category_id", type="integer", nullable=true),
     *                 @OA\Property(property="tax_rate_id", type="integer", nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Product updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        Gate::authorize('edit-product');
        try {
            $companyId = $this->getCompanyId();
            $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'description' => 'nullable|string|max:1000',
                'category_id' => 'nullable|exists:product_categories,id',
                'tax_rate_id' => 'nullable|exists:tax_rates,id',
            ]);

            $product = Product::where('company_id', $companyId)->findOrFail($id);

            $product->update([
                'name' => $request->name,
                'price' => $request->price,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'tax_rate_id' => $request->tax_rate_id,
            ]);

            return response()->json($product);
        } catch (\Throwable $e) {
            Log::error("Product update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.'], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     summary="Delete a product",
     *     description="Delete a specific product by ID",
     *     operationId="deleteProduct",
     *     tags={"Products"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="204",
     *         description="Product deleted successfully"
     *     )
     * )
     */
    public function destroy($id)
    {
        Gate::authorize('delete-product');
        try {
            $companyId = $this->getCompanyId();
            $product = Product::where('company_id', $companyId)->findOrFail($id);
            $product->delete();

            return response()->noContent();
        } catch (\Throwable $e) {
            Log::error("Product destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'], 500);
        }
    }
}
