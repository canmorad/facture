<?php

namespace App\Traits;

use App\Contracts\PayableInterface;
use App\Models\Payment;
use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Trait for models that can receive payments
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait Payable
{
    /**
     * Define the polymorphic relationship to payments
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Get completed payments only
     */
    public function completedPayments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable')
            ->where('status', 'completed')
            ->orderBy('payment_date', 'desc');
    }

    /**
     * Get the document relation (must be defined in the using model)
     */
    abstract public function document(): MorphOne;

    /**
     * Implementation of PayableInterface methods
     */
    public function isPayable(): bool
    {
        return true;
    }

    public function getPayableType(): string
    {
        return get_class($this);
    }

    public function isEligibleForPayment(): bool
    {
        return in_array($this->getStatus(), $this->getPaymentEligibleStatuses());
    }

    public function getTotalAmount(): float
    {
        return (float) $this->document?->total_ttc ?? 0;
    }

    public function getTotalDeductions(): float
    {
        // Can be overridden in specific models
        if (method_exists($this, 'deductions')) {
            return (float) $this->deductions()->sum('amount');
        }
        return 0;
    }

    public function getRemainingAmount(): float
    {
        $totalPaid = $this->completedPayments()->sum('amount');
        $totalDeductions = $this->getTotalDeductions();
        $totalAmount = $this->getTotalAmount();

        return max(0, $totalAmount - $totalDeductions - $totalPaid);
    }

    public function getCustomerId(): int
    {
        return (int) $this->document?->customer_id ?? 0;
    }

    public function getDocument()
    {
        return $this->document;
    }

    public function getStatus(): string
    {
        return $this->getAttribute('status') ?? 'DRAFT';
    }

    public function setStatus(string $status): void
    {
        $this->setAttribute('status', $status);
        $this->save();
    }

    public function markAsPaid(): void
    {
        $this->setStatus('PAID');
        $this->setAttribute('paid_at', now());
        $this->save();
    }

    public function markAsSent(): void
    {
        $this->setStatus('SENT');
        $this->save();
    }

    /**
     * Check if document is fully paid
     */
    public function isFullyPaid(): bool
    {
        return $this->getRemainingAmount() <= 0.01;
    }

    /**
     * Get payment progress percentage
     */
    public function getPaymentPercentage(): float
    {
        $totalAmount = $this->getTotalAmount();
        if ($totalAmount <= 0) {
            return 0;
        }

        $remaining = $this->getRemainingAmount();
        $paid = $totalAmount - $remaining;

        return round(($paid / $totalAmount) * 100, 2);
    }

    /**
     * Get payment summary for API responses
     */
    public function getPaymentSummary(): array
    {
        $completedPayments = $this->completedPayments()->get();

        return [
            'total_ttc' => $this->getTotalAmount(),
            'total_paid' => (float) $completedPayments->sum('amount'),
            'total_deductions' => $this->getTotalDeductions(),
            'remaining_amount' => $this->getRemainingAmount(),
            'payment_percentage' => $this->getPaymentPercentage(),
            'is_fully_paid' => $this->isFullyPaid(),
            'payments' => $completedPayments->load(['cashTransaction', 'paymentDocument']),
        ];
    }
}
