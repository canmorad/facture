<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashTransaction extends Model
{
    use HasFactory, LogsActivityTrait;

    protected $fillable = [
        'company_id',
        'cash_register_id',
        'session_id',
        'user_id',
        'type',
        'amount',
        'payment_method',
        'reference',
        'description',
        'transactionable_type',
        'transactionable_id',
        'from_cash_register_id',
        'to_cash_register_id',
        'is_verified',
        'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_verified' => 'boolean',
        'transaction_date' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromCashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'from_cash_register_id');
    }

    public function toCashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'to_cash_register_id');
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForCashRegister($query, int $cashRegisterId)
    {
        return $query->where('cash_register_id', $cashRegisterId);
    }

    public function scopeForSession($query, int $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeIn($query)
    {
        return $query->where('type', 'in');
    }

    public function scopeOut($query)
    {
        return $query->where('type', 'out');
    }

    public function scopeTransfer($query)
    {
        return $query->where('type', 'transfer');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'in' => 'Entrée',
            'out' => 'Sortie',
            'transfer' => 'Transfert',
            default => $this->type,
        };
    }
}
