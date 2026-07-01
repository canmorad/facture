<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserCompany extends Pivot
{
    protected $table = 'user_companies';

    protected $fillable = [
        'user_id',
        'company_id',
        'role_id',
    ];

    protected $casts = [
        'role_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id'); 
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}