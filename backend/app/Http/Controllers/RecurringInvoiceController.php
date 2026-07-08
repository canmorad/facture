<?php

namespace App\Http\Controllers;

use App\Models\RecurringInvoice;
use App\Models\Invoice;
use App\Models\Document;
use App\Services\DocumentCalculationService;
use App\Services\DocumentService;
use App\Services\DocumentItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RecurringInvoiceController extends Controller
{
    public function __construct(
        protected DocumentCalculationService $calculationService,
        protected DocumentService $documentService,
        protected DocumentItemService $documentItemService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage-recurring-invoices');
        try {
            $companyId = $this->getCompanyId();

            $query = RecurringInvoice::whereHas('templateDocument', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->with(['templateDocument.customer.customerable']);

            if ($request->filled('status') && in_array($request->status, ['active', 'paused', 'completed'])) {
                $query->where('status', $request->status);
            }

            $recurring = $query->orderBy('created_at', 'desc')->get();

            $recurring->transform(function ($item) {
                $item->client = $item->templateDocument?->customer;
                return $item;
            });

            return response()->json($recurring);
        } catch (\Throwable $e) {
            Log::error('RecurringInvoice index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Impossible de charger les factures récurrentes.'], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('manage-recurring-invoices');
        try {
            $companyId = $this->getCompanyId();

            $recurring = RecurringInvoice::whereHas('templateDocument', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->with(['templateDocument.customer.customerable', 'templateDocument.items'])->findOrFail($id);

            $recurring->client = $recurring->templateDocument?->customer;

            return response()->json($recurring);
        } catch (\Throwable $e) {
            Log::error('RecurringInvoice show error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Modèle récurrent introuvable.'], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage-recurring-invoices');
        try {
            $companyId = $this->getCompanyId();

            $validated = $request->validate([
                'frequency' => ['required', Rule::in(['weekly', 'monthly', 'quarterly', 'yearly'])],
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'next_run_date' => 'required|date',
                'status' => ['sometimes', Rule::in(['active', 'paused', 'completed'])],
                'customer_id' => 'required|integer|exists:customers,id',
                'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
                'items' => 'required|array|min:1',
                'items.*.designation' => 'required|string',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit_price' => 'required|numeric|min:0.01',
                'items.*.tax_rate' => 'required|numeric|min:0|max:100',
                'global_discount_type' => 'nullable|in:percentage,fixed',
                'global_discount_value' => 'nullable|numeric|min:0',
                'payment_condition' => 'nullable|string',
                'payment_mode' => 'nullable|string',
                'late_fee_interest' => 'nullable|string',
                'notes' => 'nullable|string',
                'terms' => 'nullable|string',
                'intro_text' => 'nullable|string',
                'footer_text' => 'nullable|string',
                'conclusion_text' => 'nullable|string',
            ]);

            DB::beginTransaction();

            try {
                $calculated = $this->calculationService->calculate(
                    $validated['items'],
                    $validated['global_discount_type'] ?? null,
                    $validated['global_discount_value'] ?? null
                );

                $document = $this->documentService->create([
                    'company_id' => $companyId,
                    'customer_id' => $validated['customer_id'],
                    'bank_account_id' => $validated['bank_account_id'] ?? null,
                    'parent_document_id' => null,
                    'number' => null,
                    'total_ht' => $calculated['total_ht'],
                    'total_tva' => $calculated['total_tva'],
                    'total_ttc' => $calculated['total_ttc'],
                    'global_discount_type' => $validated['global_discount_type'] ?? null,
                    'global_discount_value' => $validated['global_discount_value'] ?? 0,
                    'global_discount_amount' => $calculated['global_discount_amount'],
                    'notes' => $validated['notes'] ?? null,
                    'terms' => $validated['terms'] ?? null,
                    'intro_text' => $validated['intro_text'] ?? null,
                    'footer_text' => $validated['footer_text'] ?? null,
                    'conclusion_text' => $validated['conclusion_text'] ?? null,
                    'documentable_type' => Invoice::class,
                    'documentable_id' => 0,
                    'payment_condition' => $validated['payment_condition'] ?? null,
                    'payment_mode' => $validated['payment_mode'] ?? null,
                    'late_fee_interest' => $validated['late_fee_interest'] ?? null,
                ]);

                $invoice = Invoice::create([
                    'status' => 'DRAFT',
                    'due_date' => null,
                    'type' => 'STANDARD',
                ]);

                $document->documentable_id = $invoice->id;
                $document->save();

                $itemsWithTotals = [];
                foreach ($validated['items'] as $idx => $item) {
                    $processed = $calculated['processed_items'][$idx];
                    $lineHt = $processed['line_ht'];
                    $lineTtc = $lineHt * (1 + $item['tax_rate'] / 100);
                    $itemsWithTotals[] = array_merge($item, [
                        'calculated_ht' => $lineHt,
                        'calculated_ttc' => $lineTtc,
                    ]);
                }

                $this->documentItemService->createMany($document->id, $itemsWithTotals);

                $recurringData = [
                    'frequency' => $validated['frequency'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'] ?? null,
                    'next_run_date' => $validated['next_run_date'],
                    'status' => $validated['status'] ?? 'active',
                ];

                $recurring = RecurringInvoice::create($recurringData);

                $document->update([
                    'documentable_type' => RecurringInvoice::class,
                    'documentable_id' => $recurring->id,
                ]);

                DB::commit();

                return response()->json($recurring->load('templateDocument.customer.customerable'), 201);
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            Log::error('RecurringInvoice store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erreur lors de la création du modèle récurrent.'], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        Gate::authorize('manage-recurring-invoices');
        try {
            $companyId = $this->getCompanyId();

            $recurring = RecurringInvoice::whereHas('templateDocument', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->with('templateDocument')->findOrFail($id);

            $validated = $request->validate([
                'frequency' => ['sometimes', Rule::in(['weekly', 'monthly', 'quarterly', 'yearly'])],
                'start_date' => 'sometimes|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'next_run_date' => 'sometimes|date',
                'status' => ['sometimes', Rule::in(['active', 'paused', 'completed'])],
                'customer_id' => 'sometimes|integer|exists:customers,id',
                'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
                'items' => 'sometimes|array|min:1',
                'items.*.designation' => 'sometimes|string',
                'items.*.quantity' => 'sometimes|numeric|min:0.01',
                'items.*.unit_price' => 'sometimes|numeric|min:0.01',
                'items.*.tax_rate' => 'sometimes|numeric|min:0|max:100',
                'global_discount_type' => 'nullable|in:percentage,fixed',
                'global_discount_value' => 'nullable|numeric|min:0',
                'payment_condition' => 'nullable|string',
                'payment_mode' => 'nullable|string',
                'late_fee_interest' => 'nullable|string',
                'notes' => 'nullable|string',
                'terms' => 'nullable|string',
                'intro_text' => 'nullable|string',
                'footer_text' => 'nullable|string',
                'conclusion_text' => 'nullable|string',
            ]);

            DB::beginTransaction();

            try {
                $templateDocument = $recurring->templateDocument;

                if ($templateDocument) {
                    $docUpdateData = [];

                    if ($request->has('customer_id')) $docUpdateData['customer_id'] = $validated['customer_id'];
                    if ($request->has('bank_account_id')) $docUpdateData['bank_account_id'] = $validated['bank_account_id'];
                    if ($request->has('notes')) $docUpdateData['notes'] = $validated['notes'];
                    if ($request->has('terms')) $docUpdateData['terms'] = $validated['terms'];
                    if ($request->has('intro_text')) $docUpdateData['intro_text'] = $validated['intro_text'];
                    if ($request->has('footer_text')) $docUpdateData['footer_text'] = $validated['footer_text'];
                    if ($request->has('conclusion_text')) $docUpdateData['conclusion_text'] = $validated['conclusion_text'];
                    if ($request->has('payment_condition')) $docUpdateData['payment_condition'] = $validated['payment_condition'];
                    if ($request->has('payment_mode')) $docUpdateData['payment_mode'] = $validated['payment_mode'];
                    if ($request->has('late_fee_interest')) $docUpdateData['late_fee_interest'] = $validated['late_fee_interest'];
                    if ($request->has('global_discount_type')) $docUpdateData['global_discount_type'] = $validated['global_discount_type'];
                    if ($request->has('global_discount_value')) $docUpdateData['global_discount_value'] = $validated['global_discount_value'];

                    if ($request->has('items')) {
                        $calculated = $this->calculationService->calculate(
                            $validated['items'],
                            $validated['global_discount_type'] ?? $templateDocument->global_discount_type,
                            $validated['global_discount_value'] ?? $templateDocument->global_discount_value
                        );

                        $docUpdateData['total_ht'] = $calculated['total_ht'];
                        $docUpdateData['total_tva'] = $calculated['total_tva'];
                        $docUpdateData['total_ttc'] = $calculated['total_ttc'];
                        $docUpdateData['global_discount_amount'] = $calculated['global_discount_amount'];

                        $templateDocument->items()->delete();

                        $itemsWithTotals = [];
                        foreach ($validated['items'] as $idx => $item) {
                            $processed = $calculated['processed_items'][$idx];
                            $lineHt = $processed['line_ht'];
                            $lineTtc = $lineHt * (1 + $item['tax_rate'] / 100);
                            $itemsWithTotals[] = array_merge($item, [
                                'calculated_ht' => $lineHt,
                                'calculated_ttc' => $lineTtc,
                            ]);
                        }

                        $this->documentItemService->createMany($templateDocument->id, $itemsWithTotals);
                    }

                    if (!empty($docUpdateData)) {
                        $templateDocument->update($docUpdateData);
                    }
                }

                $recurringData = [];
                foreach (['frequency', 'start_date', 'end_date', 'next_run_date', 'status'] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $recurringData[$field] = $validated[$field];
                    }
                }

                if (!empty($recurringData)) {
                    $recurring->update($recurringData);
                }

                DB::commit();

                return response()->json($recurring->fresh(['templateDocument.customer.customerable', 'templateDocument.items']));
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            Log::error('RecurringInvoice update error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erreur lors de la mise à jour du modèle récurrent.'], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('manage-recurring-invoices');
        try {
            $companyId = $this->getCompanyId();

            $recurring = RecurringInvoice::whereHas('templateDocument', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->findOrFail($id);

            Document::where('documentable_type', RecurringInvoice::class)
                ->where('documentable_id', $recurring->id)
                ->update(['documentable_type' => Invoice::class, 'documentable_id' => 0]);

            $recurring->delete();

            return response()->json(['message' => 'Modèle récurrent supprimé.']);
        } catch (\Throwable $e) {
            Log::error('RecurringInvoice destroy error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Erreur lors de la suppression du modèle récurrent.'], 500);
        }
    }
}
