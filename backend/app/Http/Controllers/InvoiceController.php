<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Invoice;
use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $invoices = $this->invoiceService->getAll($request->query('status'));
            return response()->json($invoices);
        } catch (\Throwable $e) {
            Log::error('Invoice index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des factures.',
            ], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $companyId = $this->getCompanyId();
            $document = Document::where('id', $id)
                ->where('company_id', $companyId)
                ->where('documentable_type', Invoice::class)
                ->with(['customer', 'items', 'documentable', 'parent', 'parent.documentable', 'children.documentable', 'documentable.deductions'])
                ->first();

            if (!$document) {
                $invoice = Invoice::with(['document.customer', 'document.items', 'document.parent', 'document.children.documentable', 'deductions'])->find($id);
                if ($invoice && $invoice->document && $invoice->document->company_id == $companyId) {
                    $document = $invoice->document;
                }
            }

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facture introuvable.',
                ], 404);
            }

            $ancestorChain = $document->getAncestorChain();
            $descendantChain = $document->getDescendantChain();
            $actions = $this->invoiceService->getAvailableActions($id);
            $availableDeductions = $this->invoiceService->getAvailableDeductions($id);

            return response()->json([
                'document' => $document,
                'is_derived_from_quote' => $document->isDerivedFromQuote(),
                'ancestor_chain' => $ancestorChain,
                'descendant_chain' => $descendantChain,
                'available_actions' => $actions,
                'available_deductions' => $availableDeductions,
            ]);
        } catch (\Throwable $e) {
            Log::error("Invoice show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger les détails de la facture.',
            ], 500);
        }
    }

    public function ancestorChain(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $document = Document::where('company_id', $this->getCompanyId())
                ->where('documentable_type', Invoice::class)
                ->findOrFail($id);

            return response()->json([
                'ancestor_chain' => $document->getAncestorChain(),
                'descendant_chain' => $document->getDescendantChain(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Invoice ancestorChain error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    public function create(): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $data = $this->invoiceService->getCreationData();
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('Invoice create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors du chargement des données.',
            ], 500);
        }
    }

    public function store(InvoiceRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $document = $this->invoiceService->createDraft($request->validated());
            return response()->json($document, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Invoice store error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la facture.',
            ], 500);
        }
    }

    public function finalize(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $document = $this->invoiceService->finalize($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice finalize error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la finalisation.',
            ], 500);
        }
    }

    public function send(int $id): JsonResponse
    {
        Gate::authorize('sign-document');
        try {
            $document = $this->invoiceService->send($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice send error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi.',
            ], 500);
        }
    }

    public function actions(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $actions = $this->invoiceService->getAvailableActions($id);
            return response()->json(['actions' => $actions]);
        } catch (\Throwable $e) {
            Log::error("Invoice actions error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $deductionDepositIds = $request->input('deduction_deposit_ids');
            $document = $this->invoiceService->markPaid($id, $deductionDepositIds);
            return response()->json($document);
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
            Log::error("Invoice markPaid error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du paiement.',
            ], 500);
        }
    }

    public function markOverdue(int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $document = $this->invoiceService->markOverdue($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice markOverdue error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du marquage.',
            ], 500);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        Gate::authorize('delete-document');
        try {
            $document = $this->invoiceService->cancel($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice cancel error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'annulation.',
            ], 500);
        }
    }

    public function addDeduction(Request $request, int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $depositId = $request->input('deposit_id');
            if (!$depositId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID d\'acompte requis.',
                ], 400);
            }

            $deduction = $this->invoiceService->addDeduction($id, $depositId);
            return response()->json($deduction, 201);
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
            Log::error("Invoice addDeduction error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'ajout de la déduction.',
            ], 500);
        }
    }

    public function availableDeductions(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $deposits = $this->invoiceService->getAvailableDeductions($id);
            return response()->json(['deposits' => $deposits]);
        } catch (\Throwable $e) {
            Log::error("Invoice availableDeductions error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    public function updateMetadata(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $document = $this->invoiceService->updateMetadata($id, $request->all());
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice updateMetadata error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }

    public function updateItems(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $document = $this->invoiceService->updateItems($id, $request->input('items', []));
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Invoice updateItems error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }
}
