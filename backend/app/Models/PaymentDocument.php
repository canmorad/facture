<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class PaymentDocument extends Model
{
    use HasFactory, LogsActivityTrait, SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_id',
        'document_id',
        'type',
        'number',
        'due_date',
        'amount',
        'drawer_name',
        'drawer_bank',
        'drawer_account',
        'drawer_address',
        'beneficiary_name',
        'status',
        'bank_remittance_id',
        'payment_id',
        'deposit_date',
        'return_date',
        'return_reason',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'deposit_date' => 'date',
        'return_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function bankRemittance(): BelongsTo
    {
        return $this->belongsTo(BankRemittance::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeRemitted(Builder $query): Builder
    {
        return $query->where('status', 'remitted');
    }

    public function scopeDeposited(Builder $query): Builder
    {
        return $query->where('status', 'deposited');
    }

    public function scopeNotInRemittance(Builder $query): Builder
    {
        return $query->whereNull('bank_remittance_id');
    }

    public function scopeInRemittance(Builder $query, int $remittanceId): Builder
    {
        return $query->where('bank_remittance_id', $remittanceId);
    }

    public function scopeDueBetween(Builder $query, $startDate, $endDate): Builder
    {
        if ($startDate) {
            $query->where('due_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('due_date', '<=', $endDate);
        }
        return $query;
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('number', 'like', "%{$search}%")
                ->orWhere('drawer_name', 'like', "%{$search}%")
                ->orWhere('drawer_bank', 'like', "%{$search}%")
                ->orWhere('beneficiary_name', 'like', "%{$search}%");
        });
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'cheque' => 'Chèque',
            'lcn' => 'LCN',
            default => $this->type,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'En attente',
            'remitted' => 'Remis',
            'deposited' => 'Déposé',
            'returned' => 'Rejeté',
            'paid' => 'Encaissé',
            'cancelled' => 'Annulé',
            default => $this->status,
        };
    }

    public function canBeAddedToRemittance(): bool
    {
        return in_array($this->status, ['pending', 'returned']) && is_null($this->bank_remittance_id);
    }

    public function canBeRemovedFromRemittance(): bool
    {
        return !is_null($this->bank_remittance_id) &&
               $this->bankRemittance &&
               in_array($this->bankRemittance->status, ['DRAFT']);
    }

    public function markAsRemitted(int $remittanceId): void
    {
        $this->update([
            'status' => 'remitted',
            'bank_remittance_id' => $remittanceId,
        ]);
    }

    public function markAsDeposited(): void
    {
        $this->update([
            'status' => 'deposited',
            'deposit_date' => now(),
        ]);
    }

    public function markAsReturned(?string $reason = null): void
    {
        $this->update([
            'status' => 'returned',
            'return_date' => now(),
            'return_reason' => $reason,
        ]);
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    public function removeFromRemittance(): void
    {
        $this->update([
            'status' => 'pending',
            'bank_remittance_id' => null,
        ]);
    }
}
