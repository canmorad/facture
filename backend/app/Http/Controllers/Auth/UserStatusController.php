<?php

namespace App\Http\Controllers\Auth;

use App\Models\NumberingSerie;
use App\Models\UserCompany;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class UserStatusController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $companyId = config('app.current_company_id');

        $userCompanies = UserCompany::with(['company', 'role'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($userCompany) {
                $company = $userCompany->company;
                return [
                    'id' => $company->id,
                    'name' => $company->company_name,
                    'logo' => $company->logo ? Storage::url($company->logo) : null,
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
            $userCompany = UserCompany::where('user_id', $user->id)
                ->where('company_id', $effectiveCompanyId)
                ->first();

            if ($userCompany && $userCompany->role_id) {
                $role = Role::find($userCompany->role_id);
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
            $hasNumbering = NumberingSerie::where('company_id', $effectiveCompanyId)->exists();
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
}
