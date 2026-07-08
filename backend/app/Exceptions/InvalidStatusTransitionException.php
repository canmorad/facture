<?php

namespace App\Exceptions;

use Exception;

class InvalidStatusTransitionException extends Exception
{
    protected string $fromStatus;
    protected string $toStatus;
    protected string $documentType;

    public function __construct(string $fromStatus, string $toStatus, string $documentType = 'document')
    {
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
        $this->documentType = $documentType;
        $message = "La transition du {$documentType} de '{$fromStatus}' vers '{$toStatus}' est invalide.";
        parent::__construct($message, 422);
    }

    public function getFromStatus(): string
    {
        return $this->fromStatus;
    }

    public function getToStatus(): string
    {
        return $this->toStatus;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }
}