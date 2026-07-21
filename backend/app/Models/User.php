<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\LogsActivityTrait;
use App\Traits\HasPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, LogsActivityTrait, HasPermissions;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_companies')
            ->using(UserCompany::class)
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function getRoleForCompany(Company $company): ?Role
    {
        $pivot = $this->companies()->where('company_id', $company->id)->first()?->pivot;
        return $pivot ? Role::find($pivot->role_id) : null;
    }

    public function assignRoleToCompany(Company $company, Role $role): UserCompany
    {
        $this->companies()->detach($company->id);
        $this->companies()->attach($company->id, ['role_id' => $role->id]);
        return $this->companies()
            ->where('company_id', $company->id)
            ->first()
            ->pivot;
    }
}
