<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductCategoryController extends Controller
{
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
