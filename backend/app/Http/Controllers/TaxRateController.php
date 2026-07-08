<?php

namespace App\Http\Controllers;

use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class TaxRateController extends Controller
{
    public function index()
    {
        try {
            $companyId = $this->getCompanyId();
            $taxRates = TaxRate::where('company_id', $companyId)
                ->orderBy('is_actif', 'desc')
                ->orderBy('libelle')
                ->get();

            return response()->json($taxRates);
        } catch (\Throwable $e) {
            Log::error('TaxRate index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $companyId = $this->getCompanyId();
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
        } catch (\Throwable $e) {
            Log::error('TaxRate store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la création.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $validated = $request->validate([
                'libelle' => 'sometimes|string|max:255',
                'rate' => 'sometimes|numeric|min:0|max:100',
                'motif_exoneration' => 'nullable|string|max:255',
                'is_actif' => 'sometimes|boolean',
                'is_default' => 'sometimes|boolean',
            ]);

            $taxRate = TaxRate::where('company_id', $companyId)->findOrFail($id);

            if (isset($validated['is_default']) && $validated['is_default']) {
                TaxRate::where('company_id', $companyId)->update(['is_default' => false]);
            }

            $taxRate->update($validated);
            return response()->json($taxRate);
        } catch (\Throwable $e) {
            Log::error("TaxRate update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $companyId = $this->getCompanyId();
            $taxRate = TaxRate::where('company_id', $companyId)->findOrFail($id);
            $taxRate->delete();
            return response()->json(null, 204);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['success' => false, 'message' => 'Impossible de supprimer ce taux car il est lié à un ou plusieurs produits.'], 422);
        } catch (\Throwable $e) {
            Log::error("TaxRate destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $companyId = $this->getCompanyId();
            $taxRate = TaxRate::where('company_id', $companyId)->findOrFail($id);
            return response()->json($taxRate);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Taux de TVA introuvable.'], 404);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $companyId = $this->getCompanyId();
            $taxRate = TaxRate::where('company_id', $companyId)->findOrFail($id);
            $taxRate->is_actif = !$taxRate->is_actif;
            $taxRate->save();

            return response()->json($taxRate);
        } catch (\Throwable $e) {
            Log::error("TaxRate toggleStatus error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue.'], 500);
        }
    }
}