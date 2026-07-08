<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\DocumentTheme;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class SendDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public string $customSubject,
        public string $customMessage,
        public string $senderName,
        public string $senderEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customSubject ?: ($this->document->number . ' - ' . ($this->senderName ?: config('app.name'))),
        );
    }

    public function content(): Content
    {
        $this->document->load(['company', 'customer']);

        return new Content(
            view: 'emails.send-document',
            with: [
                'document' => $this->document,
                'customMessage' => $this->customMessage,
                'senderName' => $this->senderName,
            ],
        );
    }

    public function attachments(): array
    {
        $this->document->loadMissing(['company', 'customer.customerable', 'items', 'bankAccount']);

        // Récupérer le thème du document
        $theme = DocumentTheme::where('company_id', $this->document->company_id)->first();
        $documentType = class_basename($this->document->documentable_type);

        $docLabels = [
            'Quote' => 'DEVIS',
            'Invoice' => 'FACTURE',
            'DeliveryNote' => 'BON DE LIVRAISON',
            'PurchaseOrder' => 'BON DE COMMANDE',
            'Deposit' => 'ACOMPTE',
        ];

        $pdf = Pdf::loadView('documents.pdf', [
            'document' => $this->document,
            'theme' => $theme,
            'docLabel' => $docLabels[$documentType] ?? 'DOCUMENT',
        ]);

        $fileName = ($this->document->number ?: 'document') . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $fileName)
                ->withMime('application/pdf'),
        ];
    }
}