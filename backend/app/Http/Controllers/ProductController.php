<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\TaxRate;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
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
