<?php

namespace App\Services;

use App\Models\RecurringInvoice;
use App\Models\Invoice;
use App\Models\Document;
use App\Models\DocumentSetting;
use App\Models\Customer;
use App\Mail\SendDocumentMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RecurringInvoiceService
{
    public function __construct(
        protected NumberingService $numberingService,
        protected DocumentService $documentService,
        protected DocumentItemService $documentItemService
    ) {}

    public function processPendingRecurrences(): array
    {
        $generated = [];

        $pending = RecurringInvoice::where('status', 'active')
            ->whereDate('next_run_date', '<=', Carbon::now()->toDateString())
            ->with('templateDocument.items')
            ->get();

        foreach ($pending as $recurring) {
            try {
                $document = $this->generateInvoiceFromModel($recurring);
                $generated[] = [
                    'recurring_id' => $recurring->id,
                    'document_id' => $document->id,
                    'number' => $document->number,
                ];
            } catch (\Throwable $e) {
                Log::error('RecurringInvoice generation failed', [
                    'recurring_id' => $recurring->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $generated;
    }

    public function generateInvoiceFromModel(RecurringInvoice $recurring): Document
    {
        $templateDocument = $recurring->templateDocument;

        if (!$templateDocument) {
            throw new \RuntimeException('Aucun modèle de document associé à la facture récurrente #' . $recurring->id);
        }

        $settings = DocumentSetting::where('company_id', $templateDocument->company_id)
            ->where('document_type', 'INVOICE')
            ->first();

        DB::beginTransaction();

        try {
            // RÈGLE COMPTABLE STRICTE : Pas de numéro pour les brouillons
            // Créer d'abord en DRAFT sans numéro, puis finaliser
            $document = $this->documentService->create([
                'company_id' => $templateDocument->company_id,
                'customer_id' => $templateDocument->customer_id,
                'bank_account_id' => $templateDocument->bank_account_id,
                'parent_document_id' => null,
                'number' => null,  // NULL pour les brouillons - jamais de numéro officiel
                'total_ht' => $templateDocument->total_ht,
                'total_tva' => $templateDocument->total_tva,
                'total_ttc' => $templateDocument->total_ttc,
                'global_discount_type' => $templateDocument->global_discount_type,
                'global_discount_value' => $templateDocument->global_discount_value,
                'global_discount_amount' => $templateDocument->global_discount_amount,
                'notes' => $templateDocument->notes ?: $settings?->notes,
                'terms' => $templateDocument->terms ?: $settings?->terms,
                'intro_text' => $templateDocument->intro_text ?: $settings?->intro_text,
                'footer_text' => $templateDocument->footer_text ?: $settings?->footer_text,
                'conclusion_text' => $templateDocument->conclusion_text ?: $settings?->conclusion_text,
                'documentable_type' => Invoice::class,
                'documentable_id' => 0,
                'payment_condition' => $templateDocument->payment_condition,
                'payment_mode' => $templateDocument->payment_mode,
                'late_fee_interest' => $templateDocument->late_fee_interest,
            ]);

            $invoice = Invoice::create([
                'status' => 'DRAFT',
                'due_date' => Carbon::now()->addDays(30),
                'type' => 'STANDARD',
            ]);

            $document->documentable_id = $invoice->id;
            $document->save();

            foreach ($templateDocument->items as $item) {
                $this->documentItemService->createMany($document->id, [
                    [
                        'product_id' => $item->product_id,
                        'product_type' => $item->product_type,
                        'designation' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'tax_rate' => $item->tax_rate,
                        'discount_type' => $item->discount_type,
                        'discount_value' => $item->discount_value,
                        'calculated_ht' => $item->total_ht,
                        'calculated_ttc' => $item->total_ttc,
                    ]
                ]);
            }

            // Finaliser la facture récurrente : générer le numéro et passer au statut FINALIZED
            $invoiceNumber = $this->numberingService->generateNumber('invoice', $templateDocument->company_id);
            $this->documentService->updateNumber($document, $invoiceNumber);

            $invoice->transitionTo('FINALIZED');
            $invoice->finalized_at = now();
            $invoice->save();

            $nextRunDate = $this->calculateNextRunDate($recurring);

            $recurring->update([
                'next_run_date' => $nextRunDate,
                'last_generated_at' => now(),
                'status' => $this->determineNewStatus($recurring, $nextRunDate),
            ]);

            DB::commit();

            $this->sendInvoiceByEmail($document);

            $invoice->transitionTo('SENT');

            return $document->fresh(['customer', 'items', 'documentable']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function calculateNextRunDate(RecurringInvoice $recurring): Carbon
    {
        $base = Carbon::parse($recurring->next_run_date);

        return match ($recurring->frequency) {
            'weekly' => $base->addWeek(),
            'monthly' => $base->addMonth(),
            'quarterly' => $base->addMonths(3),
            'yearly' => $base->addYear(),
            default => $base->addMonth(),
        };
    }

    protected function determineNewStatus(RecurringInvoice $recurring, Carbon $nextRunDate): string
    {
        if ($recurring->end_date && Carbon::parse($recurring->end_date)->lt($nextRunDate)) {
            return 'completed';
        }

        return 'active';
    }

    protected function sendInvoiceByEmail(Document $document): void
    {
        try {
            $customer = Customer::with('customerable')->find($document->customer_id);

            if (!$customer || !$customer->email) {
                Log::warning('Impossible d\'envoyer la facture récurrente : email client manquant', [
                    'document_id' => $document->id,
                    'customer_id' => $document->customer_id,
                ]);
                return;
            }

            $document->load(['company', 'customer.customerable', 'items', 'bankAccount']);

            $company = $document->company;
            $companyUser = $company->owner ?? $company->users()->first();

            Mail::to($customer->email)->send(new SendDocumentMail(
                document: $document,
                customSubject: 'Facture ' . $document->number,
                customMessage: 'Veuillez trouver ci-joint votre facture.',
                senderName: $company->name ?? config('app.name'),
                senderEmail: $companyUser?->email ?? config('mail.from.address'),
            ));
        } catch (\Throwable $e) {
            Log::error('Échec de l\'envoi email pour facture récurrente', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}