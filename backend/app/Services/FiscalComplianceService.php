<?php

namespace App\Services;

use App\Exceptions\ImmutableDocumentException;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\CreditNote;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class FiscalComplianceService
{
    public function __construct(
        protected DocumentRepositoryInterface $documentRepository
    ) {}

    public function canEditDocument(Document $document): bool
    {
        if ($this->documentRepository->isLocked($document)) {
            return false;
        }

        if (!$document->documentable) {
            return true;
        }

        $status = $document->documentable->getAttribute('status');

        if ($document->documentable_type === Invoice::class) {
            return in_array($status, ['DRAFT', null]);
        }

        if ($document->documentable_type === Quote::class) {
            return in_array($status, ['DRAFT', null]);
        }

        return false;
    }

    public function canDeleteDocument(Document $document): bool
    {
        if ($this->documentRepository->isLocked($document)) {
            return false;
        }

        if ($document->parent_document_id) {
            return false;
        }

        if ($document->children()->count() > 0) {
            return false;
        }

        if (!$document->documentable) {
            return true;
        }

        $status = $document->documentable->getAttribute('status');
        return in_array($status, ['DRAFT', null]);
    }

    public function guardForEdit(Document $document): void
    {
        if (!$this->canEditDocument($document)) {
            throw new ImmutableDocumentException($document->documentable_type, $document->id);
        }
    }

    public function guardForDelete(Document $document): void
    {
        if (!$this->canDeleteDocument($document)) {
            throw new ImmutableDocumentException($document->documentable_type, $document->id);
        }
    }

    public function lockDocument(Document $document, string $reason, ?int $userId = null): void
    {
        $this->documentRepository->lock($document, $reason, $userId);
    }

    public function unlockDocument(Document $document): void
    {
        $document->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
            'lock_reason' => null,
        ]);
    }

    public function isFiscalYearClosed(Document $document): bool
    {
        return false;
    }

    public function validateSequentialNumbering(int $companyId, string $documentType, string $number): bool
    {
        $lastDocument = Document::where('company_id', $companyId)
            ->where('documentable_type', match ($documentType) {
                'invoice' => Invoice::class,
                'quote' => Quote::class,
                'credit_note' => CreditNote::class,
                default => null,
            })
            ->whereNotNull('number')
            ->orderBy('number', 'desc')
            ->first();

        if (!$lastDocument) {
            return true;
        }

        $lastNumber = (int) preg_replace('/[^0-9]/', '', $lastDocument->number);
        $currentNumber = (int) preg_replace('/[^0-9]/', '', $number);

        return $currentNumber > $lastNumber;
    }

    public function requiresCreditNoteForCancellation(Document $document): bool
    {
        if ($document->documentable_type !== Invoice::class) {
            return false;
        }

        $invoice = $document->documentable;
        $status = $invoice->getAttribute('status');

        return in_array($status, ['SENT', 'PAID', 'OVERDUE']) && !$invoice->getAttribute('has_credit_note');
    }

    public function canCancelWithoutCreditNote(Document $document): bool
    {
        if ($document->documentable_type !== Invoice::class) {
            return true;
        }

        $invoice = $document->documentable;
        $status = $invoice->getAttribute('status');

        return in_array($status, ['DRAFT', 'FINALIZED', 'CANCELLED']);
    }

    public function isLocked(Document $document): bool
    {
        return $this->documentRepository->isLocked($document);
    }
}
