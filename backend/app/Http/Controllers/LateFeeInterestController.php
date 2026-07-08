<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LateFeeInterestRequest;
use App\Models\LateFeeInterest;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LateFeeInterestController extends Controller
{
    public function index(Request $request)
    {
        try {
            $companyId = $this->getCompanyId();
            $interests = LateFeeInterest::where('company_id', $companyId)
                ->orderBy('is_default', 'desc')
                ->orderBy('label')
                ->get();

            return response()->json($interests);
        } catch (\Throwable $e) {
            Log::error('LateFeeInterest index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    public function store(LateFeeInterestRequest $request)
    {
        try {
            $companyId = $this->getCompanyId();
            $data = $request->validated();
            $data['company_id'] = $companyId;

            if (isset($data['is_default']) && $data['is_default']) {
                LateFeeInterest::where('company_id', $companyId)->where('is_default', true)->update(['is_default' => false]);
            }

            $interest = LateFeeInterest::create($data);
            return response()->json($interest, 201);
        } catch (\Throwable $e) {
            Log::error('LateFeeInterest store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la création.'], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $interest = LateFeeInterest::where('company_id', $companyId)->findOrFail($id);
            return response()->json($interest);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Intérêt de retard introuvable.'], 404);
        }
    }

    public function update(LateFeeInterestRequest $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $interest = LateFeeInterest::where('company_id', $companyId)->findOrFail($id);

            $data = $request->validated();

            if (isset($data['is_default']) && $data['is_default']) {
                LateFeeInterest::where('company_id', $companyId)
                    ->where('id', '!=', $interest->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $interest->update($data);
            return response()->json($interest);
        } catch (\Throwable $e) {
            Log::error("LateFeeInterest update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $interest = LateFeeInterest::where('company_id', $companyId)->findOrFail($id);

            if ($interest->is_default) {
                return response()->json(['success' => false, 'message' => 'Impossible de supprimer l\'intérêt de retard par défaut.'], 422);
            }

            $interest->delete();
            return response()->json(null, 204);
        } catch (\Throwable $e) {
            Log::error("LateFeeInterest destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'], 500);
        }
    }

    public function toggleActive(Request $request, $companyId, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $interest = LateFeeInterest::where('company_id', $companyId)->findOrFail($id);
            $interest->is_active = !$interest->is_active;
            $interest->save();

            return response()->json($interest);
        } catch (\Throwable $e) {
            Log::error("LateFeeInterest toggleActive error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue.'], 500);
        }
    }

    public function setDefault(Request $request, $companyId, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            LateFeeInterest::where('company_id', $companyId)->update(['is_default' => false]);
            $interest = LateFeeInterest::where('company_id', $companyId)->findOrFail($id);
            $interest->is_default = true;
            $interest->save();

            return response()->json($interest);
        } catch (\Throwable $e) {
            Log::error("LateFeeInterest setDefault error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue.'], 500);
        }
    }
}