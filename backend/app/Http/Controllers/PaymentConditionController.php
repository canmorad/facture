<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentConditionRequest;
use App\Models\PaymentCondition;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentConditionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $companyId = $this->getCompanyId();
            $conditions = PaymentCondition::where('company_id', $companyId)
                ->orderBy('is_default', 'desc')
                ->orderBy('label')
                ->get();

            return response()->json($conditions);
        } catch (\Throwable $e) {
            Log::error('PaymentCondition index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    public function store(PaymentConditionRequest $request)
    {
        try {
            $companyId = $this->getCompanyId();
            $data = $request->validated();
            $data['company_id'] = $companyId;

            if (isset($data['is_default']) && $data['is_default']) {
                PaymentCondition::where('company_id', $companyId)->where('is_default', true)->update(['is_default' => false]);
            }

            $condition = PaymentCondition::create($data);
            return response()->json($condition, 201);
        } catch (\Throwable $e) {
            Log::error('PaymentCondition store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la création.'], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $condition = PaymentCondition::where('company_id', $companyId)->findOrFail($id);
            return response()->json($condition);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Condition de paiement introuvable.'], 404);
        }
    }

    public function update(PaymentConditionRequest $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $condition = PaymentCondition::where('company_id', $companyId)->findOrFail($id);

            $data = $request->validated();

            if (isset($data['is_default']) && $data['is_default']) {
                PaymentCondition::where('company_id', $companyId)
                    ->where('id', '!=', $condition->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $condition->update($data);
            return response()->json($condition);
        } catch (\Throwable $e) {
            Log::error("PaymentCondition update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $condition = PaymentCondition::where('company_id', $companyId)->findOrFail($id);

            if ($condition->is_default) {
                return response()->json(['success' => false, 'message' => 'Impossible de supprimer la condition de paiement par défaut.'], 422);
            }

            $condition->delete();
            return response()->json(null, 204);
        } catch (\Throwable $e) {
            Log::error("PaymentCondition destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'], 500);
        }
    }

    public function toggleActive(Request $request, $companyId, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $condition = PaymentCondition::where('company_id', $companyId)->findOrFail($id);
            $condition->is_active = !$condition->is_active;
            $condition->save();

            return response()->json($condition);
        } catch (\Throwable $e) {
            Log::error("PaymentCondition toggleActive error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue.'], 500);
        }
    }

    public function setDefault(Request $request, $companyId, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            PaymentCondition::where('company_id', $companyId)->update(['is_default' => false]);
            $condition = PaymentCondition::where('company_id', $companyId)->findOrFail($id);
            $condition->is_default = true;
            $condition->save();

            return response()->json($condition);
        } catch (\Throwable $e) {
            Log::error("PaymentCondition setDefault error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue.'], 500);
        }
    }
}