<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity;

class CustomActivity extends Activity
{
    protected $table = 'activity_log';

    protected $fillable = [
        'company_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {

            $companyId = request()->input('company_id');

            if ($companyId) {
                $builder->where('company_id', $companyId);
            }

        });
    }
}