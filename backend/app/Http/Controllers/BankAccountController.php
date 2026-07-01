<?php
// app/Http/Controllers/BankAccountController.php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class BankAccountController extends Controller
{
    protected function getCompanyId(Request $request)
    {
        // 1. Vérifier le paramètre de route 'company'
        $companyId = $request->route('company');
        if ($companyId) {
            return $companyId;
        }

        // 2. Vérifier le paramètre de requête 'company_id'
        $companyId = $request->input('company_id');
        if ($companyId) {
            return $companyId;
        }

        // 3. Fallback sur la propriété currentCompanyId de l'utilisateur
        return auth()->user()->currentCompanyId ?? null;
    }

    public function index(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $accounts = BankAccount::where('company_id', $companyId)
            ->orderBy('is_default', 'desc')
            ->orderBy('label')
            ->get();

        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'rib' => 'required|string|size:24|unique:bank_accounts,rib,NULL,id,company_id,' . $companyId,
            'iban' => 'nullable|string|max:34',
            'swift' => 'nullable|string|max:11',
            'currency' => 'nullable|string|max:3',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
        ]);

        $validated['company_id'] = $companyId;
        $validated['is_active'] = $request->is_active ?? true;

        if ($validated['is_default'] ?? false) {
            BankAccount::where('company_id', $companyId)->update(['is_default' => false]);
        }

        $account = BankAccount::create($validated);
        return response()->json($account, 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $account = BankAccount::where('company_id', $companyId)->findOrFail($id);
        return response()->json($account);
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $account = BankAccount::where('company_id', $companyId)->findOrFail($id);

        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'bank_name' => 'sometimes|string|max:255',
            'rib' => [
                'sometimes',
                'string',
                'size:24',
                Rule::unique('bank_accounts', 'rib')->where('company_id', $companyId)->ignore($account->id),
            ],
            'iban' => 'nullable|string|max:34',
            'swift' => 'nullable|string|max:11',
            'currency' => 'nullable|string|max:3',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
        ]);

        if (isset($validated['is_default']) && $validated['is_default']) {
            BankAccount::where('company_id', $companyId)->update(['is_default' => false]);
        }

        $account->update($validated);
        return response()->json($account);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $account = BankAccount::where('company_id', $companyId)->findOrFail($id);

        if ($account->is_default) {
            return response()->json([
                'error' => 'Impossible de supprimer le compte bancaire par défaut.'
            ], 422);
        }

        try {
            $account->delete();
            return response()->json(null, 204);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == '23000') {
                return response()->json([
                    'error' => 'Ce compte bancaire est utilisé dans d\'autres documents et ne peut pas être supprimé.'
                ], 422);
            }
            throw $e;
        }
    }

    public function toggleActive(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $account = BankAccount::where('company_id', $companyId)->findOrFail($id);
        $account->is_active = !$account->is_active;
        $account->save();

        return response()->json($account);
    }

    public function setDefault(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $account = BankAccount::where('company_id', $companyId)->findOrFail($id);

        BankAccount::where('company_id', $companyId)->update(['is_default' => false]);

        $account->is_default = true;
        $account->save();

        return response()->json($account);
    }
}