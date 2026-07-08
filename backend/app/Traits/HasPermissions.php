<?php

namespace App\Traits;

use App\Models\Role;
use Illuminate\Support\Facades\Cache;

trait HasPermissions
{
    public function hasPermission(string $permission): bool
    {
        if ($this->isOwnerForCurrentCompany()) {
            return true;
        }

        $companyId = config('app.current_company_id');

        if (!$companyId) {
            return false;
        }

        $cacheKey = "user_{$this->id}_company_{$companyId}_has_perm_{$permission}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($companyId, $permission) {
            $role = $this->getRoleForCurrentCompany();

            if (!$role) {
                return false;
            }

            $rolePermissions = config("permissions.roles.{$role->name}", []);

            return in_array($permission, $rolePermissions, true);
        });
    }

    public function isOwnerForCurrentCompany(): bool
    {
        $companyId = config('app.current_company_id');

        if (!$companyId) {
            return false;
        }

        $cacheKey = "user_{$this->id}_company_{$companyId}_is_owner";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($companyId) {
            $role = $this->getRoleForCurrentCompany();

            return $role && $role->name === 'owner';
        });
    }

    public function getPermissionsForCurrentCompany(): array
    {
        $companyId = config('app.current_company_id');

        if (!$companyId) {
            return [];
        }

        $cacheKey = "user_{$this->id}_company_{$companyId}_permissions";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($companyId) {
            $role = $this->getRoleForCurrentCompany();

            if (!$role) {
                return [];
            }

            if ($role->name === 'owner') {
                $allPermissions = config('permissions.roles');
                return array_keys(array_flip(array_merge(...array_values($allPermissions))));
            }

            return config("permissions.roles.{$role->name}", []);
        });
    }

    private function getRoleForCurrentCompany(): ?Role
    {
        $companyId = config('app.current_company_id');

        if (!$companyId) {
            return null;
        }

        $pivot = $this->companies()
            ->where('company_id', $companyId)
            ->first()?->pivot;

        if (!$pivot || !$pivot->role_id) {
            return null;
        }

        return Role::find($pivot->role_id);
    }
}