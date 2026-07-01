<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->input('company_id') ?? auth()->user()->currentCompanyId;
        return ProductCategory::where('company_id', $companyId)->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'required|exists:Companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);
        $category = ProductCategory::create($data);
        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->input('company_id') ?? auth()->user()->currentCompanyId;
        $category = ProductCategory::where('company_id', $companyId)->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $category->update($data);
        return response()->json($category);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->input('company_id') ?? auth()->user()->currentCompanyId;
        $category = ProductCategory::where('company_id', $companyId)->findOrFail($id);

        try {
            $category->delete();
            return response()->json(null, 204);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'error' => 'Impossible de supprimer cette catégorie car elle est liée à un ou plusieurs produits.'
            ], 422);
        }
    }
}