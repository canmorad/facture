<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class BankRemittance extends Model
{
    use HasFactory, LogsActivityTrait, SoftDeletes;

    protected $fillable = [
        'company_id',
        'number',
        'bank_account_id',
        'status',
        'remittance_date',
        'total_amount',
        'document_count',
        'deposit_slip_reference',
        'finalized_at',
        'sent_at',
        'deposited_at',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'remittance_date' => 'date',
        'finalized_at' => 'datetime',
        'sent_at' => 'datetime',
        'deposited_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function paymentDocuments(): HasMany
    {
        return $this->hasMany(PaymentDocument::class);
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(PaymentDocument::class)->where('type', 'cheque');
    }

    public function lcnDocuments(): HasMany
    {
        return $this->hasMany(PaymentDocument::class)->where('type', 'lcn');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForBankAccount(Builder $query, int $bankAccountId): Builder
    {
        return $query->where('bank_account_id', $bankAccountId);
    }

    public function scopeInDateRange(Builder $query, $startDate, $endDate): Builder
    {
        if ($startDate) {
            $query->where('remittance_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('remittance_date', '<=', $endDate);
        }
        return $query;
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('number', 'like', "%{$search}%")
                ->orWhere('deposit_slip_reference', 'like', "%{$search}%")
                ->orWhereHas('bankAccount', function ($bq) use ($search) {
                    $bq->where('label', 'like', "%{$search}%")
                        ->orWhere('bank_name', 'like', "%{$search}%");
                });
        });
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'DRAFT' => 'Brouillon',
            'FINALIZED' => 'Finalisé',
            'SENT' => 'Envoyé',
            'DEPOSITED' => 'Déposé',
            'RETURNED' => 'Rejeté',
            'CANCELLED' => 'Annulé',
            default => $this->status,
        };
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, ['FINALIZED', 'SENT', 'DEPOSITED']);
    }

    public function canBeModified(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function canBeFinalized(): bool
    {
        return $this->status === 'DRAFT' && $this->document_count > 0;
    }

    public function canBeSent(): bool
    {
        return in_array($this->status, ['FINALIZED']);
    }

    public function canBeDeposited(): bool
    {
        return in_array($this->status, ['FINALIZED', 'SENT']);
    }

    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['CANCELLED', 'DEPOSITED']);
    }

    public function calculateTotals(): void
    {
        $this->load('paymentDocuments');

        $totalAmount = $this->paymentDocuments->sum('amount');
        $documentCount = $this->paymentDocuments->count();

        $this->update([
            'total_amount' => $totalAmount,
            'document_count' => $documentCount,
        ]);
    }

    public function addPaymentDocument(int $paymentDocumentId): bool
    {
        if (!$this->canBeModified()) {
            return false;
        }

        $paymentDocument = PaymentDocument::where('id', $paymentDocumentId)
            ->where('company_id', $this->company_id)
            ->whereNull('bank_remittance_id')
            ->whereIn('status', ['pending', 'returned'])
            ->first();

        if (!$paymentDocument) {
            return false;
        }

        $paymentDocument->markAsRemitted($this->id);
        $this->calculateTotals();

        return true;
    }

    public function removePaymentDocument(int $paymentDocumentId): bool
    {
        if (!$this->canBeModified()) {
            return false;
        }

        $paymentDocument = $this->paymentDocuments()->where('id', $paymentDocumentId)->first();

        if (!$paymentDocument) {
            return false;
        }

        $paymentDocument->removeFromRemittance();
        $this->calculateTotals();

        return true;
    }

    public function finalize(string $number): void
    {
        $this->update([
            'status' => 'FINALIZED',
            'number' => $number,
            'finalized_at' => now(),
        ]);

        $this->paymentDocuments->each(function ($doc) {
            if ($doc->status === 'remitted') {
                $doc->update(['status' => 'finalized']);
            }
        });
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'SENT',
            'sent_at' => now(),
        ]);
    }

    public function markAsDeposited(?string $depositSlipRef = null): void
    {
        $this->update([
            'status' => 'DEPOSITED',
            'deposited_at' => now(),
            'deposit_slip_reference' => $depositSlipRef ?? $this->deposit_slip_reference,
        ]);

        $this->paymentDocuments->each(function ($doc) {
            $doc->markAsDeposited();
        });
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'CANCELLED',
        ]);

        $this->paymentDocuments->each(function ($doc) {
            $doc->removeFromRemittance();
        });
    }

    public function getChequesCountAttribute(): int
    {
        return $this->paymentDocuments()->where('type', 'cheque')->count();
    }

    public function getLcnCountAttribute(): int
    {
        return $this->paymentDocuments()->where('type', 'lcn')->count();
    }

    public function getChequesAmountAttribute(): float
    {
        return (float) $this->paymentDocuments()->where('type', 'cheque')->sum('amount');
    }

    public function getLcnAmountAttribute(): float
    {
        return (float) $this->paymentDocuments()->where('type', 'lcn')->sum('amount');
    }
}
