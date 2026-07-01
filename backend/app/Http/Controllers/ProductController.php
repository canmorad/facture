<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\TaxRate;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    protected function getCompanyId(Request $request)
    {
        $companyId = $request->input('company_id') ?? Auth::user()->currentCompanyId;
        if (!$companyId) {
            $company = Auth::user()->companies()->first();
            return $company ? $company->id : null;
        }
        return $companyId;
    }

    public function create(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $taxRates = TaxRate::where('company_id', $companyId)->get();
        $categories = ProductCategory::where('company_id', $companyId)->get();

        return response()->json([
            'tax_rates' => $taxRates,
            'categories' => $categories,
        ]);
    }

    public function index(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $products = Product::where('company_id', $companyId)
            ->with(['category', 'taxRate'])
            ->latest()
            ->get();

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

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
    }

    public function show(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $product = Product::where('company_id', $companyId)->findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $product = Product::where('company_id', $companyId)->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'category_id' => 'nullable|exists:product_categories,id',
            'tax_rate_id' => 'nullable|exists:tax_rates,id',
        ]);

        $product->update([
            'name' => $request->name,
            'price' => $request->price,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'tax_rate_id' => $request->tax_rate_id,
        ]);

        return response()->json($product);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $product = Product::where('company_id', $companyId)->findOrFail($id);
        $product->delete();

        return response()->noContent();
    }
}