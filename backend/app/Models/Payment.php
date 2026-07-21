<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Payment extends Model
{
    use HasFactory, LogsActivityTrait, SoftDeletes;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'payable_type',
        'payable_id',
        'customer_id',
        'payment_mode',
        'amount',
        'payment_date',
        'reference',
        'notes',
        'status',
        'cash_transaction_id',
        'payment_document_id',
        'document_relationship_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Polymorphic relationship to any payable document
     */
    public function payable()
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class);
    }

    public function paymentDocument(): BelongsTo
    {
        return $this->belongsTo(PaymentDocument::class);
    }

    public function documentRelationship(): BelongsTo
    {
        return $this->belongsTo(DocumentRelationship::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForInvoice(Builder $query, int $invoiceId): Builder
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function scopeForPayable(Builder $query, string $payableType, int $payableId): Builder
    {
        return $query->where('payable_type', $payableType)
            ->where('payable_id', $payableId);
    }

    public function scopeForDocument(Builder $query, int $documentId): Builder
    {
        return $query->whereHas('payable.document', function ($q) use ($documentId) {
            $q->where('id', $documentId);
        });
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeWithPaymentMode(Builder $query, string $mode): Builder
    {
        return $query->where('payment_mode', $mode);
    }

    public function scopeBetweenDates(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    // Accessors
    public function getPaymentModeLabelAttribute(): string
    {
        return match($this->payment_mode) {
            'espece' => 'Espèces',
            'cheque' => 'Chèque',
            'lcn' => 'LCN',
            'virement' => 'Virement',
            'carte' => 'Carte',
            default => $this->payment_mode,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'En attente',
            'completed' => 'Complété',
            'cancelled' => 'Annulé',
            default => $this->status,
        };
    }

    // Methods
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'payment_date' => $this->payment_date ?? now(),
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function markAsPending(): void
    {
        $this->update(['status' => 'pending']);
    }

    public function requiresCashTransaction(): bool
    {
        return $this->payment_mode === 'espece';
    }

    public function requiresPaymentDocument(): bool
    {
        return in_array($this->payment_mode, ['cheque', 'lcn']);
    }

    public function isDirectPayment(): bool
    {
        return in_array($this->payment_mode, ['virement', 'carte']);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
