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

    public function getAvailableUsers()
    {
        Gate::authorize('invite-users');
        try {
            $companyId = $this->getCompanyId();
            $userId = auth()->id();

            // Get all company IDs where the current user is an owner
            $ownerCompanyIds = UserCompany::where('user_id', $userId)
                ->whereHas('role', function ($query) {
                    $query->where('name', 'owner');
                })
                ->pluck('company_id');

            if ($ownerCompanyIds->isEmpty()) {
                // If user is not an owner of any company, return empty array
                return response()->json(['users' => []]);
            }

            // Get all user IDs that work with this owner across their companies
            $collaboratorIds = UserCompany::whereIn('company_id', $ownerCompanyIds)
                ->where('user_id', '!=', $userId)
                ->pluck('user_id')
                ->unique();

            // Exclude users already in current company
            $currentCompanyUserIds = UserCompany::where('company_id', $companyId)
                ->pluck('user_id');

            $availableUsers = User::whereIn('id', $collaboratorIds)
                ->whereNotIn('id', $currentCompanyUserIds)
                ->get(['id', 'name', 'email', 'is_active', 'email_verified_at'])
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'is_active' => $user->is_active,
                        'email_verified_at' => $user->email_verified_at,
                    ];
                });

            return response()->json(['users' => $availableUsers]);
        } catch (\Throwable $e) {
            Log::error('Get available users error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue.'], 500);
        }
    }

    public function quickAddUser(Request $request)
    {
        Gate::authorize('invite-users');
        try {
            $companyId = $this->getCompanyId();

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'role_id' => 'required|exists:roles,id',
            ]);

            // Check if user already exists in current company
            $existing = UserCompany::where('user_id', $validated['user_id'])
                ->where('company_id', $companyId)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur fait déjà partie de cette entreprise.',
                ], 422);
            }

            // Get the user to verify they exist
            $user = User::findOrFail($validated['user_id']);

            // Create the company membership
            UserCompany::create([
                'user_id' => $validated['user_id'],
                'company_id' => $companyId,
                'role_id' => $validated['role_id'],
            ]);

            // Get role information
            $role = Role::find($validated['role_id']);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur ajouté avec succès.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'is_owner' => $role && $role->name === 'owner',
                    'role' => $role ? $role->name : 'viewer',
                    'role_label' => $role ? $this->getRoleLabel($role->name) : 'Observateur',
                    'email_verified_at' => $user->email_verified_at,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            Log::error('Quick add user Runtime error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Quick add user error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de l\'ajout de l\'utilisateur.'], 500);
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
