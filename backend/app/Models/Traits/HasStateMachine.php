<?php

namespace App\Models\Traits;

use App\Exceptions\InvalidStatusTransitionException;

trait HasStateMachine
{
    public function transitionTo(string $newStatus): self
    {
        $currentStatus = $this->status ?? null;
        $allowedTransitions = static::getTransitions();

        if ($currentStatus === null) {
            $this->status = $newStatus;
            $this->save();
            return $this;
        }

        if (!isset($allowedTransitions[$currentStatus])) {
            throw new InvalidStatusTransitionException($currentStatus ?? 'unknown', $newStatus, class_basename($this));
        }

        if (!in_array($newStatus, $allowedTransitions[$currentStatus])) {
            throw new InvalidStatusTransitionException($currentStatus, $newStatus, class_basename($this));
        }

        $this->status = $newStatus;

        $auditTimestamps = [
            'FINALIZED' => 'finalized_at',
            'SENT' => 'sent_at',
            'SIGNED' => 'signed_at',
            'CONFIRMED' => 'confirmed_at',
            'DELIVERED' => 'delivered_at',
            'PAID' => 'paid_at',
            'APPLIED' => 'applied_at',
        ];

        if (isset($auditTimestamps[$newStatus]) && in_array($auditTimestamps[$newStatus], $this->getFillable())) {
            $this->{$auditTimestamps[$newStatus]} = now();
        }

        $this->save();

        return $this;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $currentStatus = $this->status ?? null;

        if ($currentStatus === null) {
            return $newStatus === 'DRAFT';
        }

        $allowedTransitions = static::getTransitions();

        if (!isset($allowedTransitions[$currentStatus])) {
            return false;
        }

        return in_array($newStatus, $allowedTransitions[$currentStatus]);
    }
}