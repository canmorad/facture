<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Role;
use App\Models\UserCompany;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(protected InvitationService $invitationService) {}

    public function index()
    {
        Gate::authorize('invite-users');
        try {
            $companyId = $this->getCompanyId();

            $users = User::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->with(['companies' => function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->withPivot('role_id');
            }])->get();

            $roles = Role::whereIn('name', ['manager', 'accountant', 'assistant-accountant', 'viewer'])->get();

            $pendingInvitations = Invitation::where('company_id', $companyId)
                ->where('expires_at', '>', now())
                ->with('role')
                ->get()
                ->map(function ($invitation) {
                    return [
                        'id' => $invitation->id,
                        'email' => $invitation->email,
                        'role' => $invitation->role->name,
                        'role_label' => $this->getRoleLabel($invitation->role->name),
                        'expires_at' => $invitation->expires_at,
                        'pending' => true,
                    ];
                });

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
                'pending_invitations' => $pendingInvitations,
            ]);
        } catch (\Throwable $e) {
            Log::error('User index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    public function invite(Request $request)
    {
        Gate::authorize('invite-users');
        try {
            $companyId = $this->getCompanyId();

            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'role_id' => 'required|exists:roles,id',
            ]);

            $result = $this->invitationService->invite($validated['email'], $validated['role_id'], $companyId);

            return response()->json($result, 201);
        } catch (\RuntimeException $e) {
            Log::error('User invite Runtime error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('User invite error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de l\'invitation.'], 500);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        Gate::authorize('invite-users');
        try {
            $companyId = $this->getCompanyId();

            $user = User::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->with(['companies' => function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->withPivot('role_id');
            }])->findOrFail($id);

            $pivot = $user->companies->first()?->pivot;
            $role = $pivot ? Role::find($pivot->role_id) : null;

            if ($role && $role->name === 'owner') {
                return response()->json(['success' => false, 'message' => 'Impossible de désactiver le propriétaire.'], 422);
            }

            $user->is_active = !$user->is_active;
            $user->save();

            return response()->json(['id' => $user->id, 'is_active' => $user->is_active]);
        } catch (\Throwable $e) {
            Log::error("User toggleStatus error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue.'], 500);
        }
    }

    public function destroy($id)
    {
        Gate::authorize('invite-users');
        try {
            $companyId = $this->getCompanyId();

            $user = User::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->with(['companies' => function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->withPivot('role_id');
            }])->findOrFail($id);

            $pivot = $user->companies->first()?->pivot;
            $role = $pivot ? Role::find($pivot->role_id) : null;

            if ($role && $role->name === 'owner') {
                return response()->json(['success' => false, 'message' => 'Impossible de supprimer le propriétaire.'], 422);
            }

            $user->companies()->detach($companyId);

            return response()->json(null, 204);
        } catch (\Throwable $e) {
            Log::error("User destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'], 500);
        }
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
