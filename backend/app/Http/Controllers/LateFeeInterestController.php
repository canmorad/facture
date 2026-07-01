<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LateFeeInterestRequest;
use App\Models\LateFeeInterest;
use App\Models\Company;
use Illuminate\Http\Request;

class LateFeeInterestController extends Controller
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
        $interests = $company->lateFeeInterests()
            ->orderBy('is_default', 'desc')
            ->orderBy('label')
            ->get();

        return response()->json($interests);
    }

    public function store(LateFeeInterestRequest $request)
    {
        $company = $this->getCompany($request);
        $data = $request->validated();
        $data['company_id'] = $company->id;

        if (isset($data['is_default']) && $data['is_default']) {
            $company->lateFeeInterests()->where('is_default', true)->update(['is_default' => false]);
        }

        $interest = LateFeeInterest::create($data);
        return response()->json($interest, 201);
    }

    public function show(Request $request, $id)
    {
        $company = $this->getCompany($request);
        $interest = LateFeeInterest::where('company_id', $company->id)->findOrFail($id);
        return response()->json($interest);
    }

    public function update(LateFeeInterestRequest $request, $id)
    {
        $company = $this->getCompany($request);
        $interest = LateFeeInterest::where('company_id', $company->id)->findOrFail($id);

        $data = $request->validated();

        if (isset($data['is_default']) && $data['is_default']) {
            $company->lateFeeInterests()
                ->where('id', '!=', $interest->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $interest->update($data);
        return response()->json($interest);
    }

    public function destroy(Request $request, $id)
    {
        $company = $this->getCompany($request);
        $interest = LateFeeInterest::where('company_id', $company->id)->findOrFail($id);

        if ($interest->is_default) {
            return response()->json([
                'error' => 'Impossible de supprimer l\'intérêt de retard par défaut.'
            ], 422);
        }

        $interest->delete();
        return response()->json(null, 204);
    }

    public function toggleActive(Request $request, $companyId, $id)
    {
        $company = Company::findOrFail($companyId);
        $interest = LateFeeInterest::where('company_id', $company->id)->findOrFail($id);
        $interest->is_active = !$interest->is_active;
        $interest->save();

        return response()->json($interest);
    }

    public function setDefault(Request $request, $companyId, $id)
    {
        $company = Company::findOrFail($companyId);
        $company->lateFeeInterests()->update(['is_default' => false]);
        $interest = LateFeeInterest::where('company_id', $company->id)->findOrFail($id);
        $interest->is_default = true;
        $interest->save();

        return response()->json($interest);
    }
}