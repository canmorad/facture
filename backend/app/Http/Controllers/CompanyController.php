<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\UserCompany;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Traits\MediaUpload;
use Illuminate\Support\Facades\Gate;

class CompanyController extends Controller
{
    use MediaUpload;

    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'signature' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'ice' => 'nullable|string|max:15',
            'if' => 'nullable|string|max:8',
            'rc' => 'nullable|string|max:255',
            'patente' => 'nullable|string|max:255',
            'cnss' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'address' => 'required|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();

        $logo_path = null;
        $signature_path = null;

        if ($request->hasFile('logo')) {
            $logo_path = $this->upload($request->file('logo'), 'logos');
        }

        if ($request->hasFile('signature')) {
            $signature_path = $this->upload($request->file('signature'), 'signatures');
        }

        $company = Company::create([
            'company_name' => $request->company_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'if' => $request->if,
            'ice' => $request->ice,
            'rc' => $request->rc,
            'patente' => $request->patente,
            'cnss' => $request->cnss,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
            'country' => $request->country ?? 'Maroc',
            'website' => $request->website,
            'logo' => $logo_path,
            'signature' => $signature_path,
        ]);

        $ownerRole = Role::firstOrCreate(
            ['name' => 'owner'],
            ['description' => 'First user who registers. Master access to everything.']
        );

        UserCompany::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $ownerRole->id,
        ]);

        return response()->json($company, 201);
    }

    public function update(Request $request)
    {
        Gate::authorize('view-settings');
        $request->validate([
            'company_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'signature' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'ice' => 'nullable|string|max:15',
            'if' => 'nullable|string|max:8',
            'rc' => 'nullable|string|max:255',
            'patente' => 'nullable|string|max:255',
            'cnss' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'address' => 'required|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();
        $companyId = config('app.current_company_id');
        $company = Company::find($companyId);

        if (!$company) {
            return response()->json(['error' => 'Entreprise introuvable.'], 404);
        }

        $userCompany = UserCompany::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->first();

        if (!$userCompany) {
            return response()->json(['error' => 'Vous n\'êtes pas autorisé à modifier cette entreprise.'], 403);
        }

        $logo_path = $company->logo ?? null;
        $signature_path = $company->signature ?? null;

        if ($request->hasFile('logo')) {
            if ($logo_path) {
                $this->delete($logo_path);
            }
            $logo_path = $this->upload($request->file('logo'), 'logos');
        }

        if ($request->hasFile('signature')) {
            if ($signature_path) {
                $this->delete($signature_path);
            }
            $signature_path = $this->upload($request->file('signature'), 'signatures');
        }

        $company->update([
            'company_name' => $request->company_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'if' => $request->if,
            'ice' => $request->ice,
            'rc' => $request->rc,
            'patente' => $request->patente,
            'cnss' => $request->cnss,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
            'country' => $request->country ?? 'Maroc',
            'website' => $request->website,
            'logo' => $logo_path,
            'signature' => $signature_path,
        ]);

        return response()->json(['message' => 'Settings saved successfully'], 200);
    }

    public function show(Request $request)
    {
        Gate::authorize('view-settings');
        $companyId = config('app.current_company_id');

        if (!$companyId) {
            return response()->json(null, 200);
        }

        $user = auth()->user();
        $userCompany = UserCompany::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->first();

        if (!$userCompany) {
            return response()->json(null, 200);
        }

        $settings = Company::find($companyId);

        if (!$settings) {
            return response()->json(null, 200);
        }

        $settings->logo = $settings->logo ? Storage::url($settings->logo) : null;
        $settings->signature = $settings->signature ? Storage::url($settings->signature) : null;

        return response()->json($settings);
    }

    public function userOrganizations(Request $request)
    {
        $user = $request->user();

        $userCompanies = UserCompany::with(['company', 'role'])
            ->where('user_id', $user->id)
            ->get();

        return $userCompanies->map(function ($userCompany) {
            $company = $userCompany->company;
            return [
                'id' => $company->id,
                'name' => $company->company_name,
                'logo' => $company->logo ? Storage::url($company->logo) : null,
                'is_owner' => $userCompany->role && $userCompany->role->name === 'owner',
                'role' => $userCompany->role ? $userCompany->role->name : 'Membre',
            ];
        });
    }

    public function leaveOrganization(Request $request, $id)
    {
        $user = $request->user();

        $membership = UserCompany::where('user_id', $user->id)
            ->where('company_id', $id)
            ->first();

        if (!$membership) {
            return response()->json([
                'error' => 'Vous n\'appartenez pas à cette organisation.'
            ], 404);
        }

        $role = Role::find($membership->role_id);
        if ($role && $role->name === 'owner') {
            return response()->json([
                'error' => 'Le propriétaire ne peut pas quitter son organisation.'
            ], 422);
        }

        $membership->delete();

        return response()->json(null, 204);
    }
}
