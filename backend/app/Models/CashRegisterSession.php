<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterSession extends Model
{
    use HasFactory, LogsActivityTrait;

    protected $fillable = [
        'company_id',
        'cash_register_id',
        'opened_by_user_id',
        'closed_by_user_id',
        'opening_balance',
        'expected_closing_balance',
        'actual_closing_balance',
        'discrepancy',
        'status',
        'opened_at',
        'closed_at',
        'opening_notes',
        'closing_notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'expected_closing_balance' => 'decimal:2',
        'actual_closing_balance' => 'decimal:2',
        'discrepancy' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'session_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForCashRegister($query, int $cashRegisterId)
    {
        return $query->where('cash_register_id', $cashRegisterId);
    }

    public function calculateTotalIn(): float
    {
        return (float) $this->transactions()->where('type', 'in')->sum('amount');
    }

    public function calculateTotalOut(): float
    {
        return (float) $this->transactions()->where('type', 'out')->sum('amount');
    }

    public function calculateTotalTransfersIn(): float
    {
        return (float) $this->transactions()
            ->where('type', 'transfer')
            ->where('to_cash_register_id', $this->cash_register_id)
            ->sum('amount');
    }

    public function calculateTotalTransfersOut(): float
    {
        return (float) $this->transactions()
            ->where('type', 'transfer')
            ->where('from_cash_register_id', $this->cash_register_id)
            ->sum('amount');
    }

    public function calculateExpectedClosingBalance(): float
    {
        $totalIn = $this->calculateTotalIn();
        $totalOut = $this->calculateTotalOut();
        $transferIn = $this->calculateTotalTransfersIn();
        $transferOut = $this->calculateTotalTransfersOut();

        return (float) $this->opening_balance + $totalIn - $totalOut + $transferIn - $transferOut;
    }

    public function calculateDiscrepancy(): float
    {
        if ($this->actual_closing_balance === null) {
            return 0.0;
        }

        return (float) $this->actual_closing_balance - $this->expected_closing_balance;
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'open' => 'Ouvert',
            'closed' => 'Clôturé',
            default => $this->status,
        };
    }
}
