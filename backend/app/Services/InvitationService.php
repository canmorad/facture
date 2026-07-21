<?php

namespace App\Services;

use App\Mail\InvitationMail;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCompany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationService
{
    public function invite(string $email, int $roleId, int $companyId): array
    {
        $company = Company::findOrFail($companyId);
        $role = Role::findOrFail($roleId);

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            return $this->handleExistingUser($existingUser, $company, $role);
        }

        return $this->handleNewUser($email, $company, $role);
    }

    public function verifyToken(string $token): array
    {
        $invitation = Invitation::where('token', $token)->first();

        if (!$invitation) {
            return ['valid' => false, 'message' => 'Token d\'invitation introuvable.'];
        }

        if ($invitation->isExpired()) {
            $invitation->delete();
            return ['valid' => false, 'message' => 'Cette invitation a expiré.'];
        }

        return [
            'valid' => true,
            'email' => $invitation->email,
            'company_name' => $invitation->company->company_name,
            'role' => $invitation->role->name,
        ];
    }

    public function accept(string $token, string $name, string $password): User
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if ($invitation->isExpired()) {
            $invitation->delete();
            throw new \RuntimeException('Cette invitation a expiré.');
        }

        $user = User::create([
            'name' => $name,
            'email' => $invitation->email,
            'password' => Hash::make($password),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        UserCompany::create([
            'user_id' => $user->id,
            'company_id' => $invitation->company_id,
            'role_id' => $invitation->role_id,
        ]);

        $invitation->delete();

        return $user;
    }

    public function acceptForExistingUser(User $user, string $token): array
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if ($invitation->isExpired()) {
            $invitation->delete();
            throw new \RuntimeException('Cette invitation a expiré.');
        }

        // Check if the invitation email matches the user's email
        if ($invitation->email !== $user->email) {
            throw new \RuntimeException('Cette invitation n\'est pas destinée à votre adresse email.');
        }

        // Check if already linked to this company
        $existingMembership = UserCompany::where('user_id', $user->id)
            ->where('company_id', $invitation->company_id)
            ->first();

        if ($existingMembership) {
            $company = $invitation->company;
            $role = $existingMembership->role;
            $invitation->delete();
            return [
                'company' => array_merge($company->toArray(), ['role' => $role->toArray()]),
                'user' => $user->load('companies'),
                'already_linked' => true,
            ];
        }

        // Create the company membership
        UserCompany::create([
            'user_id' => $user->id,
            'company_id' => $invitation->company_id,
            'role_id' => $invitation->role_id,
        ]);

        $company = $invitation->company;
        $role = Role::find($invitation->role_id);
        $invitation->delete();

        return [
            'company' => array_merge($company->toArray(), ['role' => $role->toArray()]),
            'user' => $user->load('companies'),
            'already_linked' => false,
        ];
    }

    protected function handleExistingUser(User $user, Company $company, Role $role): array
    {
        $existingMembership = UserCompany::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->first();

        if ($existingMembership) {
            throw new \RuntimeException('Cet utilisateur fait déjà partie de cette entreprise.');
        }

        UserCompany::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $role->id,
        ]);

        try {
            Mail::to($user->email)->send(new InvitationMail(
                email: $user->email,
                token: 'existing',
                company: $company,
                isExistingUser: true,
            ));
        } catch (\Throwable $e) {
            Log::error('Invitation mail send failed (existing user): ' . $e->getMessage(), ['exception' => $e]);
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'is_owner' => $role->name === 'owner',
                'role' => $role->name,
                'role_label' => $this->getRoleLabel($role->name),
                'email_verified_at' => $user->email_verified_at,
            ],
        ];
    }

    protected function handleNewUser(string $email, Company $company, Role $role): array
    {
        $existingInvitation = Invitation::where('email', $email)
            ->where('company_id', $company->id)
            ->first();

        if ($existingInvitation) {
            if ($existingInvitation->isExpired()) {
                $existingInvitation->delete();
            } else {
                throw new \RuntimeException('Une invitation est déjà en attente pour cet email.');
            }
        }

        $token = Str::random(64);

        Invitation::create([
            'email' => $email,
            'company_id' => $company->id,
            'role_id' => $role->id,
            'token' => $token,
            'expires_at' => now()->addHours(48),
        ]);

        try {
            Mail::to($email)->send(new InvitationMail(
                email: $email,
                token: $token,
                company: $company,
                isExistingUser: false,
            ));
        } catch (\Throwable $e) {
            Log::error('Invitation mail send failed (new user): ' . $e->getMessage(), ['exception' => $e]);
            // Still return success response even if email fails
        }

        return [
            'user' => [
                'id' => null,
                'name' => $email,
                'email' => $email,
                'is_active' => false,
                'is_owner' => false,
                'role' => $role->name,
                'role_label' => $this->getRoleLabel($role->name),
                'email_verified_at' => null,
                'pending' => true,
            ],
        ];
    }

    protected function getRoleLabel(string $roleName): string
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