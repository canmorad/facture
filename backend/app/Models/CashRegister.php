<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashRegister extends Model
{
    use HasFactory, LogsActivityTrait, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'currency',
        'opening_balance',
        'current_balance',
        'is_active',
        'is_default',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashRegisterSession::class);
    }

    public function activeSession(): HasMany
    {
        return $this->hasMany(CashRegisterSession::class)->where('status', 'open');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'cash' => 'Espèces',
            'bank' => 'Banque',
            'vault' => 'Coffre-fort',
            'petty_cash' => 'Caisse Petite Monnaie',
            default => $this->type,
        };
    }

    public function calculateActualBalance(): float
    {
        $totalIn = (float) $this->transactions()
            ->where('type', 'in')
            ->sum('amount');

        $totalTransfersIn = (float) $this->transactions()
            ->where('type', 'transfer')
            ->where('to_cash_register_id', $this->id)
            ->sum('amount');

        $totalOut = (float) $this->transactions()
            ->where('type', 'out')
            ->sum('amount');

        $totalTransfersOut = (float) $this->transactions()
            ->where('type', 'transfer')
            ->where('from_cash_register_id', $this->id)
            ->sum('amount');

        return (float) $this->opening_balance + $totalIn + $totalTransfersIn - $totalOut - $totalTransfersOut;
    }

    public function scopeWithCalculatedBalance($query)
    {
        return $query->select('*')
            ->selectRaw('
                opening_balance + COALESCE((
                    SELECT COALESCE(SUM(
                        CASE
                            WHEN type = \'in\' THEN amount
                            WHEN type = \'transfer\' AND to_cash_register_id = cash_registers.id THEN amount
                            ELSE 0
                        END
                    ), 0)
                    FROM cash_transactions
                    WHERE cash_register_id = cash_registers.id
                ), 0) - COALESCE((
                    SELECT COALESCE(SUM(
                        CASE
                            WHEN type = \'out\' THEN amount
                            WHEN type = \'transfer\' AND from_cash_register_id = cash_registers.id THEN amount
                            ELSE 0
                        END
                    ), 0)
                    FROM cash_transactions
                    WHERE cash_register_id = cash_registers.id
                ), 0) as calculated_balance
            ');
    }
}
