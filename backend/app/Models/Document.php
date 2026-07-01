<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class Document extends Model
{
    use LogsActivity;
    protected $fillable = [
        'company_id',
        'customer_id',
        'bank_account_id',
        'number',
        'total_ht',
        'total_tva',
        'total_ttc',
        'global_discount_type',
        'global_discount_value',
        'global_discount_amount',
        'notes',
        'terms',
        'intro_text',
        'footer_text',
        'conclusion_text',
        'documentable_type',
        'documentable_id',
        'payment_condition',
        'payment_mode',
        'late_fee_interest',
    ];

    protected $casts = [
        'total_ht' => 'float',
        'total_tva' => 'float',
        'total_ttc' => 'float',
        'global_discount_value' => 'float',
        'global_discount_amount' => 'float',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(__CLASS__, 'document_links', 'parent_id', 'child_id')
            ->withTimestamps();
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(__CLASS__, 'document_links', 'child_id', 'parent_id')
            ->withTimestamps();
    }


    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->company_id = $this->company_id;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('documents')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {

                $number = $this->number ?? 'Sans numéro';
                $userName = auth()->user()?->name ?? 'Système';

                return match ($eventName) {
                    'created' => "Document [{$number}] a été créé par {$userName}",
                    'updated' => "Document [{$number}] a été mis à jour par {$userName}",
                    'deleted' => "Document [{$number}] a été supprimé par {$userName}",
                    default => "Document [{$number}] a été modifié par {$userName}",
                };
            });
    }
}