<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\B2bCustomer;
use App\Models\B2cCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    protected function getCompanyId(Request $request)
    {
        $companyId = $request->input('company_id') ?? auth()->user()->currentCompanyId;
        if (!$companyId) {
            $company = auth()->user()->companies()->first();
            return $company ? $company->id : null;
        }
        return $companyId;
    }

    public function index(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $customers = Customer::with('customerable')
            ->where('company_id', $companyId)
            ->latest()
            ->get();

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:Companies,id',
            'type' => ['required', Rule::in(['b2b', 'b2c'])],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address_street' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $company = auth()->user()->companies()->find($request->company_id);
        if (!$company) {
            return response()->json(['error' => 'Entreprise non trouvée ou non autorisée'], 403);
        }

        return DB::transaction(function () use ($request, $company) {
            if ($request->type === 'b2b') {
                $b2bData = $request->validate([
                    'legal_name' => 'required|string|max:255',
                    'ice' => 'required|string|max:15|unique:b2b_customers,ice',
                    'rc' => 'nullable|string|max:255',
                    'if' => 'nullable|string|max:255',
                    'patente' => 'nullable|string|max:255',
                ]);

                $customerable = B2bCustomer::create($b2bData);
            } else {
                $b2cData = $request->validate([
                    'name' => 'required|string|max:255',
                    'cin' => 'nullable|string|max:50|unique:b2c_customers,cin',
                ]);

                $customerable = B2cCustomer::create($b2cData);
            }

            $customer = Customer::create([
                'company_id' => $company->id,
                'email' => $request->email,
                'phone' => $request->phone,
                'address_street' => $request->address_street,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country ?? 'Maroc',
                'notes' => $request->notes,
                'is_active' => $request->is_active ?? true,
                'type' => $request->type,
                'customerable_type' => get_class($customerable),
                'customerable_id' => $customerable->id,
            ]);

            return response()->json($customer->load('customerable'), 201);
        });
    }

    public function show(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $customer = Customer::with('customerable')
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json($customer);
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $customer = Customer::with('customerable')
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $request->validate([
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address_street' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        return DB::transaction(function () use ($request, $customer) {
            $customer->update([
                'email' => $request->email,
                'phone' => $request->phone,
                'address_street' => $request->address_street,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country ?? 'Maroc',
                'notes' => $request->notes,
                'is_active' => $request->is_active ?? true,
            ]);

            $customerable = $customer->customerable;
            if ($customer->type === 'b2b') {
                $data = $request->validate([
                    'legal_name' => 'required|string|max:255',
                    'ice' => [
                        'required',
                        'string',
                        'max:15',
                        Rule::unique('b2b_customers', 'ice')->ignore($customer->customerable_id),
                    ],
                    'rc' => 'nullable|string|max:255',
                    'if' => 'nullable|string|max:255',
                    'patente' => 'nullable|string|max:255',
                ]);
                $customerable->update($data);
            } else {
                $data = $request->validate([
                    'name' => 'required|string|max:255',
                    'cin' => [
                        'nullable',
                        'string',
                        'max:50',
                        Rule::unique('b2c_customers', 'cin')->ignore($customer->customerable_id),
                    ],
                ]);
                $customerable->update($data);
            }

            return response()->json($customer->load('customerable'));
        });
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $customer = Customer::where('company_id', $companyId)->findOrFail($id);

        DB::transaction(function () use ($customer) {
            $customer->customerable->delete();
            $customer->delete();
        });

        return response()->json(null, 204);
    }
}