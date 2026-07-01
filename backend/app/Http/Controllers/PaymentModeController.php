<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentModeRequest;
use App\Models\PaymentMode;
use App\Models\Company;
use Illuminate\Http\Request;

class PaymentModeController extends Controller
{
    protected function getCompany(Request $request): Company
    {
        $companyId = $request->route('company');
        if ($companyId) {
            return Company::findOrFail($companyId);
        }
        return $request->user()->currentCompany;
    }

    public function index(Request $request)
    {
        $company = $this->getCompany($request);
        $modes = $company->paymentModes()
            ->orderBy('is_default', 'desc')
            ->orderBy('label')
            ->get();

        return response()->json($modes);
    }

    public function store(PaymentModeRequest $request)
    {
        $company = $this->getCompany($request);
        $data = $request->validated();
        $data['company_id'] = $company->id;

        if (isset($data['is_default']) && $data['is_default']) {
            $company->paymentModes()->where('is_default', true)->update(['is_default' => false]);
        }

        $mode = PaymentMode::create($data);
        return response()->json($mode, 201);
    }

    public function show(Request $request, $id)
    {
        $company = $this->getCompany($request);
        $mode = PaymentMode::where('company_id', $company->id)->findOrFail($id);
        return response()->json($mode);
    }

    public function update(PaymentModeRequest $request, $id)
    {
        $company = $this->getCompany($request);
        $mode = PaymentMode::where('company_id', $company->id)->findOrFail($id);

        $data = $request->validated();

        if (isset($data['is_default']) && $data['is_default']) {
            $company->paymentModes()
                ->where('id', '!=', $mode->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $mode->update($data);
        return response()->json($mode);
    }

    public function destroy(Request $request, $id)
    {
        $company = $this->getCompany($request);
        $mode = PaymentMode::where('company_id', $company->id)->findOrFail($id);

        if ($mode->is_default) {
            return response()->json([
                'error' => 'Impossible de supprimer le mode de paiement par défaut.'
            ], 422);
        }

        $mode->delete();
        return response()->json(null, 204);
    }

    public function toggleActive(Request $request, $companyId, $id)
    {
        $company = Company::findOrFail($companyId);
        $mode = PaymentMode::where('company_id', $company->id)->findOrFail($id);
        $mode->is_active = !$mode->is_active;
        $mode->save();

        return response()->json($mode);
    }

    public function setDefault(Request $request, $companyId, $id)
    {
        $company = Company::findOrFail($companyId);
        $company->paymentModes()->update(['is_default' => false]);
        $mode = PaymentMode::where('company_id', $company->id)->findOrFail($id);
        $mode->is_default = true;
        $mode->save();

        return response()->json($mode);
    }
}