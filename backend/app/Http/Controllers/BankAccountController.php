<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class BankAccountController extends Controller
{
    public function index()
    {
        try {
            $companyId = $this->getCompanyId();
            $accounts = BankAccount::where('company_id', $companyId)
                ->orderBy('is_default', 'desc')
                ->orderBy('label')
                ->get();

            return response()->json($accounts);
        } catch (\Throwable $e) {
            Log::error('BankAccount index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $companyId = $this->getCompanyId();
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
        } catch (\Throwable $e) {
            Log::error('BankAccount store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la création.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $companyId = $this->getCompanyId();
            $account = BankAccount::where('company_id', $companyId)->findOrFail($id);
            return response()->json($account);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Compte bancaire introuvable.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $companyId = $this->getCompanyId();
            $validated = $request->validate([
                'label' => 'sometimes|string|max:255',
                'bank_name' => 'sometimes|string|max:255',
                'rib' => [
                    'sometimes', 'string', 'size:24',
                    Rule::unique('bank_accounts', 'rib')->where('company_id', $companyId)->ignore($id),
                ],
                'iban' => 'nullable|string|max:34',
                'swift' => 'nullable|string|max:11',
                'currency' => 'nullable|string|max:3',
                'is_active' => 'sometimes|boolean',
                'is_default' => 'sometimes|boolean',
            ]);

            $account = BankAccount::where('company_id', $companyId)->findOrFail($id);

            if (isset($validated['is_default']) && $validated['is_default']) {
                BankAccount::where('company_id', $companyId)->update(['is_default' => false]);
            }

            $account->update($validated);
            return response()->json($account);
        } catch (\Throwable $e) {
            Log::error("BankAccount update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $companyId = $this->getCompanyId();
            $account = BankAccount::where('company_id', $companyId)->findOrFail($id);

            if ($account->is_default) {
                return response()->json(['success' => false, 'message' => 'Impossible de supprimer le compte bancaire par défaut.'], 422);
            }

            $account->delete();
            return response()->json(null, 204);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == '23000') {
                return response()->json(['success' => false, 'message' => 'Ce compte bancaire est utilisé dans d\'autres documents et ne peut pas être supprimé.'], 422);
            }
            Log::error("BankAccount destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'], 500);
        } catch (\Throwable $e) {
            Log::error("BankAccount destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'], 500);
        }
    }

    public function toggleActive($id)
    {
        try {
            $companyId = $this->getCompanyId();
            $account = BankAccount::where('company_id', $companyId)->findOrFail($id);
            $account->is_active = !$account->is_active;
            $account->save();

            return response()->json($account);
        } catch (\Throwable $e) {
            Log::error("BankAccount toggleActive error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue.'], 500);
        }
    }

    public function setDefault($id)
    {
        try {
            $companyId = $this->getCompanyId();
            $account = BankAccount::where('company_id', $companyId)->findOrFail($id);

            BankAccount::where('company_id', $companyId)->update(['is_default' => false]);

            $account->is_default = true;
            $account->save();

            return response()->json($account);
        } catch (\Throwable $e) {
            Log::error("BankAccount setDefault error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue.'], 500);
        }
    }
}