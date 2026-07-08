<?php

namespace App\Exceptions;

use Exception;

class ImmutableDocumentException extends Exception
{
    protected string $sourceType;
    protected int $documentId;

    public function __construct(string $sourceType, int $documentId)
    {
        $this->sourceType = $sourceType;
        $this->documentId = $documentId;
        $message = "Ce document (#{$documentId}) dérive d'un {$sourceType}. Les lignes et totaux sont immuables.";
        parent::__construct($message, 422);
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getDocumentId(): int
    {
        return $this->documentId;
    }
}