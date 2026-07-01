<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentConditionRequest;
use App\Models\PaymentCondition;
use App\Models\Company;
use Illuminate\Http\Request;

class PaymentConditionController extends Controller
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
        $conditions = $company->paymentConditions()
            ->orderBy('is_default', 'desc')
            ->orderBy('label')
            ->get();

        return response()->json($conditions);
    }

    public function store(PaymentConditionRequest $request)
    {
        $company = $this->getCompany($request);
        $data = $request->validated();
        $data['company_id'] = $company->id;

        if (isset($data['is_default']) && $data['is_default']) {
            $company->paymentConditions()->where('is_default', true)->update(['is_default' => false]);
        }

        $condition = PaymentCondition::create($data);

        return response()->json($condition, 201);
    }

    public function show(Request $request, $id)
    {
        $company = $this->getCompany($request);
        $condition = PaymentCondition::where('company_id', $company->id)->findOrFail($id);
        return response()->json($condition);
    }

    public function update(PaymentConditionRequest $request, $id)
    {
        $company = $this->getCompany($request);
        $condition = PaymentCondition::where('company_id', $company->id)->findOrFail($id);

        $data = $request->validated();

        if (isset($data['is_default']) && $data['is_default']) {
            $company->paymentConditions()
                ->where('id', '!=', $condition->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $condition->update($data);

        return response()->json($condition);
    }

    public function destroy(Request $request, $id)
    {
        $company = $this->getCompany($request);
        $condition = PaymentCondition::where('company_id', $company->id)->findOrFail($id);

        if ($condition->is_default) {
            return response()->json([
                'error' => 'Impossible de supprimer la condition de paiement par défaut.'
            ], 422);
        }

        $condition->delete();

        return response()->json(null, 204);
    }

    public function toggleActive(Request $request, $companyId, $id)
    {
        $company = Company::findOrFail($companyId);
        $condition = PaymentCondition::where('company_id', $company->id)->findOrFail($id);
        $condition->is_active = !$condition->is_active;
        $condition->save();

        return response()->json($condition);
    }

    public function setDefault(Request $request, $companyId, $id)
    {
        $company = Company::findOrFail($companyId);
        $company->paymentConditions()->update(['is_default' => false]);
        $condition = PaymentCondition::where('company_id', $company->id)->findOrFail($id);
        $condition->is_default = true;
        $condition->save();

        return response()->json($condition);
    }
}