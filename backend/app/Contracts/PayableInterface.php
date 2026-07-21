<?php

namespace App\Contracts;

interface PayableInterface
{
    /**
     * Check if this document can receive payments
     */
    public function isPayable(): bool;

    /**
     * Get the document type identifier for payments
     */
    public function getPayableType(): string;

    /**
     * Get the eligible statuses for receiving payments
     * @return array<string>
     */
    public function getPaymentEligibleStatuses(): array;

    /**
     * Check if the document is currently eligible for payments
     */
    public function isEligibleForPayment(): bool;

    /**
     * Get the total TTC amount of the document
     */
    public function getTotalAmount(): float;

    /**
     * Get the total deductions (already paid amounts, advances, etc.)
     */
    public function getTotalDeductions(): float;

    /**
     * Get the remaining amount to be paid
     */
    public function getRemainingAmount(): float;

    /**
     * Get the customer ID associated with this document
     */
    public function getCustomerId(): int;

    /**
     * Get the Document model associated with this payable
     */
    public function getDocument();

    /**
     * Mark the document as paid
     */
    public function markAsPaid(): void;

    /**
     * Mark the document as sent (if applicable)
     */
    public function markAsSent(): void;

    /**
     * Get the current status
     */
    public function getStatus(): string;

    /**
     * Update the status
     */
    public function setStatus(string $status): void;
}
