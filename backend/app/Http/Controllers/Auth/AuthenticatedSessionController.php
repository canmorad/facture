<?php
// app/Http/Controllers/Auth/AuthenticatedSessionController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{

    public function store(LoginRequest $request): JsonResponse
{
    $request->authenticate();
    $request->session()->regenerate();

    $user = Auth::user()->load('companies');

    $companyId = config('app.current_company_id');

    $userCompanies = \App\Models\UserCompany::with(['company', 'role'])
        ->where('user_id', $user->id)
        ->get()
        ->map(function ($userCompany) {
            $company = $userCompany->company;
            return [
                'id' => $company->id,
                'name' => $company->company_name,
                'logo' => $company->logo ? \Illuminate\Support\Facades\Storage::url($company->logo) : null,
                'is_owner' => $userCompany->role && $userCompany->role->name === 'owner',
                'role' => $userCompany->role ? $userCompany->role->name : 'Membre',
            ];
        });

    $hasCompany = $userCompanies->isNotEmpty();

    // Use header company id, fallback to first company
    $effectiveCompanyId = $companyId ?? ($hasCompany ? $userCompanies->first()['id'] : null);

    $isOwner = false;
    $permissions = [];

    if ($effectiveCompanyId) {
        $userCompany = \App\Models\UserCompany::where('user_id', $user->id)
            ->where('company_id', $effectiveCompanyId)
            ->first();

        if ($userCompany && $userCompany->role_id) {
            $role = \App\Models\Role::find($userCompany->role_id);
            if ($role) {
                $isOwner = $role->name === 'owner';

                if ($isOwner) {
                    $allPermissions = config('permissions.roles');
                    $permissions = array_keys(array_flip(array_merge(...array_values($allPermissions))));
                } else {
                    $permissions = config("permissions.roles.{$role->name}", []);
                }
            }
        }
    }

    $hasNumbering = false;

    if ($hasCompany && $effectiveCompanyId) {
        $hasNumbering = \App\Models\NumberingSerie::where('company_id', $effectiveCompanyId)->exists();
    }

    return response()->json([
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'is_owner' => $isOwner,
            'companies' => $userCompanies,
        ],
        'permissions' => $permissions,
        'email_verified' => $user->hasVerifiedEmail(),
        'has_company' => $hasCompany,
        'has_numbering' => $hasNumbering,
    ]);
}


    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }
}