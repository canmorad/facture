<?php

namespace App\Http\Controllers;

use App\Services\PurchaseInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private PurchaseInvoiceService $service,
    ) {}

    /**
     * Analyse un fichier facture avec Gemini AI
     * POST /api/purchase-invoices/analyze
     */
    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,bmp,tiff|max:20480',
        ]);

        try {
            $result = $this->service->analyzeFile($request->file('file'));

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 500);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to analyze file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        try {
            $invoices = $this->service->index();
            return response()->json($invoices);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve purchase invoices.',
            ], 500);
        }
    }

    public function create(): JsonResponse
    {
        try {
            $data = $this->service->getCreationData();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load creation data.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = config('app.current_company_id');

        $validator = validator($request->all(), [
            'fournisseur_id' => 'required|integer|exists:suppliers,id',
            'supplier_invoice_number' => 'required|string|max:100',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after:invoice_date',
            'items' => 'required|array|min:1',
            'items.*.designation' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'apply_withholding_tax' => 'nullable|boolean',
            'global_discount_type' => 'nullable|in:percentage,fixed',
            'global_discount_value' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,bmp,tiff|max:10240',
        ]);

        if ($validator->fails()) {
            Log::error('Facture d\'achat validation error', [
                'errors' => $validator->errors()->toArray(),
                'request' => $request->all(),
                'company_id' => $companyId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation: ' . $validator->errors()->first(),
            ], 422);
        }

        $supplier = \App\Models\Fournisseur::where('id', $request->fournisseur_id)
            ->where('company_id', $companyId)
            ->first();

        if (!$supplier) {
            Log::error('Facture d\'achat supplier not found in company', [
                'fournisseur_id' => $request->fournisseur_id,
                'company_id' => $companyId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Le fournisseur sélectionné est invalide.',
            ], 422);
        }

        try {
            $invoice = $this->service->create(
                $request->all(),
                $request->file('file')
            );

            return response()->json($invoice, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Facture d\'achat store error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'company_id' => $companyId,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la facture.',
            ], 500);
        }
    }

    public function show(string|int $id): JsonResponse
    {
        try {
            $invoice = \App\Models\PurchaseInvoice::with([
                'fournisseur',
                'items.product',
                'validator'
            ])->findOrFail($id);

            $companyId = config('app.current_company_id');
            if ($invoice->company_id !== $companyId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            return response()->json($invoice);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase invoice not found.',
            ], 404);
        }
    }

    public function update(Request $request, string|int $id): JsonResponse
    {
        $companyId = config('app.current_company_id');

        $request->validate([
            'fournisseur_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('company_id', $companyId),
            ],
            'supplier_invoice_number' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date|after:invoice_date',
            'items' => 'nullable|array|min:1',
            'items.*.designation' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'apply_withholding_tax' => 'nullable|boolean',
            'global_discount_type' => 'nullable|in:percentage,fixed',
            'global_discount_value' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,bmp,tiff|max:10240',
        ]);

        try {
            $invoice = \App\Models\PurchaseInvoice::findOrFail($id);

            if ($invoice->company_id !== $companyId) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $updated = $this->service->update(
                $invoice,
                $request->all(),
                $request->file('file')
            );

            return response()->json($updated);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Facture non trouvée.',
            ], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Facture d\'achat update error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'company_id' => $companyId,
                'invoice_id' => $id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la modification de la facture.',
            ], 500);
        }
    }

    public function validate(string|int $id): JsonResponse
    {
        try {
            $invoice = \App\Models\PurchaseInvoice::findOrFail($id);
            $validated = $this->service->validate($invoice);

            return response()->json($validated);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark a purchase invoice as paid
     * PUT /api/purchase-invoices/{id}/mark-paid
     */
    public function markPaid(string|int $id): JsonResponse
    {
        try {
            $invoice = \App\Models\PurchaseInvoice::findOrFail($id);
            $marked = $this->service->markAsPaid($invoice);

            return response()->json($marked);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("PurchaseInvoice markPaid error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du marquage comme payée.',
            ], 500);
        }
    }

    /**
     * Mark a purchase invoice as unpaid (revert to validated status)
     * PUT /api/purchase-invoices/{id}/mark-unpaid
     */
    public function markUnpaid(string|int $id): JsonResponse
    {
        try {
            $invoice = \App\Models\PurchaseInvoice::findOrFail($id);
            $marked = $this->service->markAsUnpaid($invoice);

            return response()->json($marked);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("PurchaseInvoice markUnpaid error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du marquage comme impayée.',
            ], 500);
        }
    }

    /**
     * Cancel a purchase invoice
     * PUT /api/purchase-invoices/{id}/cancel
     */
    public function cancel(string|int $id): JsonResponse
    {
        try {
            $invoice = \App\Models\PurchaseInvoice::findOrFail($id);
            $cancelled = $this->service->cancel($invoice);

            return response()->json($cancelled);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("PurchaseInvoice cancel error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'annulation.',
            ], 500);
        }
    }

    public function destroy(string|int $id): JsonResponse
    {
        try {
            $invoice = \App\Models\PurchaseInvoice::findOrFail($id);

            $companyId = config('app.current_company_id');
            if ($invoice->company_id !== $companyId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            if ($invoice->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft invoices can be deleted.',
                ], 400);
            }

            $invoice->delete();

            return response()->json([
                'success' => true,
                'message' => 'Purchase invoice deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete purchase invoice.',
            ], 500);
        }
    }
}