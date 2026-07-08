<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentTheme;
use App\Services\QuoteService;
use App\Services\InvoiceService;
use App\Services\DeliveryNoteService;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentPreviewController extends Controller
{
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->getCompanyId();

            $document = Document::with([
                'customer',
                'items',
                'documentable',
                'parent',
                'parent.documentable',
                'children.documentable',
                'company',
                'bankAccount',
            ])->find($id);

            if (!$document) {
                return response()->json(['success' => false, 'message' => 'Document introuvable.'], 404);
            }

            if ($companyId && $document->company_id != $companyId) {
                return response()->json(['success' => false, 'message' => 'Document non autorisé.'], 403);
            }

            $resolvedCompanyId = $document->company_id;

            $theme = DocumentTheme::where('company_id', $resolvedCompanyId)->first();
            if (!$theme) {
                $theme = DocumentTheme::create([
                    'company_id' => $resolvedCompanyId,
                    'font_family' => 'Nunito',
                    'primary_color' => '#062121',
                    'background_pattern' => 'none',
                    'table_border_style' => 'sharp',
                    'table_line_style' => 'standard',
                ]);
            }

            $company = $document->company;
            $company->logo = $company->logo ? Storage::url($company->logo) : null;
            $bankAccount = $document->bankAccount;

            $documentType = class_basename($document->documentable_type);
            $documentData = $this->formatDocumentData($document, $documentType);

            $ancestorChain = $document->getAncestorChain();
            $descendantChain = $document->getDescendantChain();

            $actions = $this->getAvailableActions($document, $documentType);

            return response()->json([
                'document' => $documentData,
                'document_type' => $documentType,
                'theme' => $theme,
                'company' => $company,
                'bank_account' => $bankAccount,
                'ancestor_chain' => $ancestorChain,
                'descendant_chain' => $descendantChain,
                'available_actions' => $actions,
            ]);
        } catch (\Throwable $e) {
            Log::error("DocumentPreview show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger l\'aperçu du document.',
            ], 500);
        }
    }

    protected function getAvailableActions(Document $document, string $documentType): array
    {
        try {
            return match ($documentType) {
                'Quote' => app(QuoteService::class)->getAvailableActions($document->id),
                'Invoice' => app(InvoiceService::class)->getAvailableActions($document->id),
                'DeliveryNote' => app(DeliveryNoteService::class)->getAvailableActions($document->id),
                'PurchaseOrder' => app(PurchaseOrderService::class)->getAvailableActions($document->id),
                default => [],
            };
        } catch (\Throwable $e) {
            Log::error("DocumentPreview getAvailableActions error: " . $e->getMessage());
            return [];
        }
    }

    protected function formatDocumentData(Document $document, string $type): array
    {
        $dateField = match ($type) {
            'Quote' => $document->finalized_at ?? $document->created_at,
            'Invoice' => $document->finalized_at ?? $document->created_at,
            'DeliveryNote' => $document->created_at,
            'PurchaseOrder' => $document->finalized_at ?? $document->created_at,
            'Deposit' => $document->finalized_at ?? $document->created_at,
            'CreditNote' => $document->finalized_at ?? $document->created_at,
            default => $document->created_at,
        };

        $dueDateField = null;
        if ($type === 'Invoice' && $document->documentable) {
            $dueDateField = $document->documentable->due_date ?? null;
        }
        if ($type === 'Quote' && $document->documentable) {
            $dueDateField = $document->documentable->valid_until ?? null;
        }
        if ($type === 'DeliveryNote' && $document->documentable) {
            $dueDateField = $document->documentable->delivery_date ?? null;
        }
        if ($type === 'PurchaseOrder' && $document->documentable) {
            $dueDateField = $document->documentable->expected_date ?? null;
        }

        $doc = $document->documentable;

        $items = $document->items->map(function ($item) {
            $totalHt = ($item->quantity ?? 0) * ($item->unit_price ?? 0);
            $taxRate = $item->tax_rate ?? 0;
            $totalTtc = $totalHt * (1 + $taxRate / 100);

            return [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'product_type' => $item->product_type,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate' => $taxRate,
                'total_ht' => $item->total_ht ?? $totalHt,
                'total_ttc' => $item->total_ttc ?? $totalTtc,
                'discount_type' => $item->discount_type,
                'discount_value' => $item->discount_value,
            ];
        });

        $customer = null;
        if ($document->customer) {
            $customerable = $document->customer->customerable;
            $customerName = '';
            if ($customerable && $document->customer->type === 'b2b') {
                $customerName = $customerable->legal_name ?? '';
            } elseif ($customerable && $document->customer->type === 'b2c') {
                $customerName = $customerable->name ?? '';
            }

            $customer = [
                'name' => $customerName,
                'address_street' => $document->customer->address_street,
                'city' => $document->customer->city,
                'country' => $document->customer->country,
                'postal_code' => $document->customer->postal_code,
                'email' => $document->customer->email,
                'phone' => $document->customer->phone,
                'ice' => $document->customer->ice,
                'type' => $document->customer->type,
            ];
        }

        return [
            'id' => $document->id,
            'number' => $document->number,
            'type' => $type,
            'status' => $doc->status ?? null,
            'date' => $dateField,
            'due_date' => $dueDateField,
            'sent_at' => $doc->sent_at ?? null,
            'paid_at' => $doc->paid_at ?? null,
            'valid_until' => $doc->valid_until ?? null,
            'created_at' => $document->created_at,
            'updated_at' => $document->updated_at,
            'finalized_at' => $document->finalized_at,
            'total_ht' => $document->total_ht,
            'total_tva' => $document->total_tva,
            'total_ttc' => $document->total_ttc,
            'global_discount_type' => $document->global_discount_type,
            'global_discount_value' => $document->global_discount_value,
            'global_discount_amount' => $document->global_discount_amount,
            'notes' => $document->notes,
            'terms' => $document->terms,
            'intro_text' => $document->intro_text,
            'footer_text' => $document->footer_text,
            'conclusion_text' => $document->conclusion_text,
            'payment_condition' => $document->payment_condition,
            'payment_mode' => $document->payment_mode,
            'late_fee_interest' => $document->late_fee_interest,
            'customer' => $customer,
            'items' => $items->toArray(),
        ];
    }
}