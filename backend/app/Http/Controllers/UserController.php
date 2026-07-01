<?php
// app/Http/Controllers/UserController.php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\UserCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    protected function getCompanyId(Request $request)
    {
        return $request->input('company_id') ?? auth()->user()->currentCompanyId;
    }

    public function index(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $users = User::whereHas('companies', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->with(['companies' => function ($query) use ($companyId) {
            $query->where('company_id', $companyId)->withPivot('role_id');
        }])->get();

        $roles = Role::whereIn('name', ['manager', 'accountant', 'assistant-accountant', 'viewer'])->get();

        return response()->json([
            'users' => $users->map(function ($user) use ($companyId) {
                $pivot = $user->companies->first()?->pivot;
                $role = $pivot ? Role::find($pivot->role_id) : null;
                $isOwner = $role && $role->name === 'owner';
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'is_owner' => $isOwner,
                    'role' => $role ? $role->name : 'viewer',
                    'role_label' => $role ? $this->getRoleLabel($role->name) : 'Observateur',
                    'email_verified_at' => $user->email_verified_at,
                ];
            }),
            'roles' => $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => $this->getRoleLabel($role->name),
                ];
            }),
        ]);
    }

    public function invite(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            $password = Str::random(12);
            $user = User::create([
                'name' => explode('@', $validated['email'])[0],
                'email' => $validated['email'],
                'password' => Hash::make($password),
                'is_active' => true,
            ]);
        }

        $existing = UserCompany::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'Cet utilisateur fait déjà partie de cette organisation.'
            ], 422);
        }

        UserCompany::create([
            'user_id' => $user->id,
            'company_id' => $companyId,
            'role_id' => $validated['role_id'],
        ]);

        return response()->json([
            'message' => 'Invitation envoyée avec succès.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'is_owner' => false,
                'role' => Role::find($validated['role_id'])->name,
                'role_label' => $this->getRoleLabel(Role::find($validated['role_id'])->name),
                'email_verified_at' => $user->email_verified_at,
            ],
        ], 201);
    }

    public function toggleStatus(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $user = User::whereHas('companies', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->with(['companies' => function ($query) use ($companyId) {
            $query->where('company_id', $companyId)->withPivot('role_id');
        }])->findOrFail($id);

        $pivot = $user->companies->first()?->pivot;
        $role = $pivot ? Role::find($pivot->role_id) : null;

        if ($role && $role->name === 'owner') {
            return response()->json([
                'error' => 'Impossible de désactiver le propriétaire.'
            ], 422);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'id' => $user->id,
            'is_active' => $user->is_active,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $user = User::whereHas('companies', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->with(['companies' => function ($query) use ($companyId) {
            $query->where('company_id', $companyId)->withPivot('role_id');
        }])->findOrFail($id);

        $pivot = $user->companies->first()?->pivot;
        $role = $pivot ? Role::find($pivot->role_id) : null;

        if ($role && $role->name === 'owner') {
            return response()->json([
                'error' => 'Impossible de supprimer le propriétaire.'
            ], 422);
        }

        $user->companies()->detach($companyId);

        return response()->json(null, 204);
    }

    private function getRoleLabel($roleName)
    {
        return [
            'owner' => 'Propriétaire',
            'manager' => 'Responsable',
            'accountant' => 'Comptable',
            'assistant-accountant' => 'Assistant Comptable',
            'viewer' => 'Observateur',
        ][$roleName] ?? $roleName;
    }
}