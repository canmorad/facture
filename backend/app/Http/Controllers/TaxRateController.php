<?php

namespace App\Http\Controllers;

use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxRateController extends Controller
{
    protected function getCompanyId(Request $request)
    {
        return $request->input('company_id') ?? auth()->user()->currentCompanyId;
    }

    public function index(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $taxRates = TaxRate::where('company_id', $companyId)
            ->orderBy('is_actif', 'desc')
            ->orderBy('libelle')
            ->get();

        return response()->json($taxRates);
    }

    public function store(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $validated = $request->validate([
            'libelle' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'motif_exoneration' => 'nullable|string|max:255',
            'is_actif' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
        ]);

        $validated['company_id'] = $companyId;
        $validated['is_actif'] = $request->is_actif ?? true;
        $validated['is_default'] = $request->is_default ?? false;

        if ($validated['is_default']) {
            TaxRate::where('company_id', $companyId)->update(['is_default' => false]);
        }

        $taxRate = TaxRate::create($validated);
        return response()->json($taxRate, 201);
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $taxRate = TaxRate::where('company_id', $companyId)->findOrFail($id);

        $validated = $request->validate([
            'libelle' => 'sometimes|string|max:255',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'motif_exoneration' => 'nullable|string|max:255',
            'is_actif' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
        ]);

        if (isset($validated['is_default']) && $validated['is_default']) {
            TaxRate::where('company_id', $companyId)->update(['is_default' => false]);
        }

        $taxRate->update($validated);
        return response()->json($taxRate);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $taxRate = TaxRate::where('company_id', $companyId)->findOrFail($id);

        try {
            $taxRate->delete();
            return response()->json(null, 204);
        } catch (\Illuminate\Database\QueryException $e) {
            // Integrity constraint violation
            return response()->json([
                'error' => 'Impossible de supprimer ce taux car il est lié à un ou plusieurs produits.'
            ], 422);
        }
    }

    public function show(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $taxRate = TaxRate::where('company_id', $companyId)->findOrFail($id);
        return response()->json($taxRate);
    }

    public function toggleStatus(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $taxRate = TaxRate::where('company_id', $companyId)->findOrFail($id);
        $taxRate->is_actif = !$taxRate->is_actif;
        $taxRate->save();

        return response()->json($taxRate);
    }


}