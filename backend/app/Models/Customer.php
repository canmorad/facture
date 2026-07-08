<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    use LogsActivityTrait;

    protected $with = ['customerable'];

    protected $fillable = [
        'company_id',
        'email',
        'phone',
        'address_street',
        'city',
        'postal_code',
        'country',
        'notes',
        'is_active',
        'type',
        'customerable_type',
        'customerable_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function customerable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getNameAttribute(): string
    {
        if ($this->customerable) {
            return $this->customerable->legal_name ?? $this->customerable->name ?? 'Client';
        }

        return 'Client';
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'b2b' ? 'Professionnel' : 'Particulier';
    }
}