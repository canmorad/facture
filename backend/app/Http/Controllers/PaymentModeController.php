<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentModeRequest;
use App\Models\PaymentMode;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentModeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $companyId = $this->getCompanyId();
            $modes = PaymentMode::where('company_id', $companyId)
                ->orderBy('is_default', 'desc')
                ->orderBy('label')
                ->get();

            return response()->json($modes);
        } catch (\Throwable $e) {
            Log::error('PaymentMode index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    public function store(PaymentModeRequest $request)
    {
        try {
            $companyId = $this->getCompanyId();
            $data = $request->validated();
            $data['company_id'] = $companyId;

            if (isset($data['is_default']) && $data['is_default']) {
                PaymentMode::where('company_id', $companyId)->where('is_default', true)->update(['is_default' => false]);
            }

            $mode = PaymentMode::create($data);
            return response()->json($mode, 201);
        } catch (\Throwable $e) {
            Log::error('PaymentMode store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la création.'], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $mode = PaymentMode::where('company_id', $companyId)->findOrFail($id);
            return response()->json($mode);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Mode de paiement introuvable.'], 404);
        }
    }

    public function update(PaymentModeRequest $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $mode = PaymentMode::where('company_id', $companyId)->findOrFail($id);

            $data = $request->validated();

            if (isset($data['is_default']) && $data['is_default']) {
                PaymentMode::where('company_id', $companyId)
                    ->where('id', '!=', $mode->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $mode->update($data);
            return response()->json($mode);
        } catch (\Throwable $e) {
            Log::error("PaymentMode update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $mode = PaymentMode::where('company_id', $companyId)->findOrFail($id);

            if ($mode->is_default) {
                return response()->json(['success' => false, 'message' => 'Impossible de supprimer le mode de paiement par défaut.'], 422);
            }

            $mode->delete();
            return response()->json(null, 204);
        } catch (\Throwable $e) {
            Log::error("PaymentMode destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'], 500);
        }
    }

    public function toggleActive(Request $request, $companyId, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $mode = PaymentMode::where('company_id', $companyId)->findOrFail($id);
            $mode->is_active = !$mode->is_active;
            $mode->save();

            return response()->json($mode);
        } catch (\Throwable $e) {
            Log::error("PaymentMode toggleActive error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue.'], 500);
        }
    }

    public function setDefault(Request $request, $companyId, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            PaymentMode::where('company_id', $companyId)->update(['is_default' => false]);
            $mode = PaymentMode::where('company_id', $companyId)->findOrFail($id);
            $mode->is_default = true;
            $mode->save();

            return response()->json($mode);
        } catch (\Throwable $e) {
            Log::error("PaymentMode setDefault error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue.'], 500);
        }
    }
}