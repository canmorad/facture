<?php

namespace App\Http\Controllers;

use App\Models\PaymentDocument;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class PaymentDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $companyId = $this->getCompanyId();
            $perPage = (int) $request->input('per_page', 10);
            $type = $request->input('type');
            $status = $request->input('status');

            $query = PaymentDocument::where('company_id', $companyId)
                ->with(['customer', 'bankRemittance']);

            if ($type && in_array($type, ['cheque', 'lcn'])) {
                $query->where('type', $type);
            }

            if ($status) {
                $query->where('status', $status);
            }

            $documents = $query->orderBy('created_at', 'desc')->paginate($perPage);
            return response()->json($documents);
        } catch (\Throwable $e) {
            Log::error('PaymentDocument index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $validated = $request->validate([
                'type' => 'required|in:cheque,lcn',
                'customer_id' => 'nullable|exists:customers,id',
                'document_id' => 'nullable|exists:documents,id',
                'number' => 'required|string|max:255',
                'due_date' => 'required|date',
                'amount' => 'required|numeric|min:0',
                'drawer_name' => 'nullable|string|max:255',
                'drawer_bank' => 'nullable|string|max:255',
                'drawer_account' => 'nullable|string|max:255',
                'drawer_address' => 'nullable|string',
                'beneficiary_name' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
            ]);

            $companyId = $this->getCompanyId();

            $document = PaymentDocument::create([
                'company_id' => $companyId,
                'customer_id' => $validated['customer_id'] ?? null,
                'document_id' => $validated['document_id'] ?? null,
                'type' => $validated['type'],
                'number' => $validated['number'],
                'due_date' => $validated['due_date'],
                'amount' => $validated['amount'],
                'drawer_name' => $validated['drawer_name'] ?? null,
                'drawer_bank' => $validated['drawer_bank'] ?? null,
                'drawer_account' => $validated['drawer_account'] ?? null,
                'drawer_address' => $validated['drawer_address'] ?? null,
                'beneficiary_name' => $validated['beneficiary_name'] ?? null,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json($document->load(['customer', 'bankRemittance']), 201);
        } catch (\Throwable $e) {
            Log::error('PaymentDocument store error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $companyId = $this->getCompanyId();
            $document = PaymentDocument::where('id', $id)
                ->where('company_id', $companyId)
                ->with(['customer', 'bankRemittance', 'document'])
                ->firstOrFail();

            return response()->json($document);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document introuvable.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error("PaymentDocument show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $validated = $request->validate([
                'number' => 'sometimes|string|max:255',
                'due_date' => 'sometimes|date',
                'amount' => 'sometimes|numeric|min:0',
                'drawer_name' => 'nullable|string|max:255',
                'drawer_bank' => 'nullable|string|max:255',
                'drawer_account' => 'nullable|string|max:255',
                'drawer_address' => 'nullable|string',
                'beneficiary_name' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
            ]);

            $companyId = $this->getCompanyId();

            $document = PaymentDocument::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            if ($document->bank_remittance_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce document est déjà inclus dans une remise.',
                ], 422);
            }

            $document->update($validated);

            return response()->json($document->load(['customer', 'bankRemittance']));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document introuvable.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error("PaymentDocument update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('delete-document');
        try {
            $companyId = $this->getCompanyId();

            $document = PaymentDocument::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            if ($document->bank_remittance_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce document est déjà inclus dans une remise.',
                ], 422);
            }

            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document supprimé avec succès.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document introuvable.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error("PaymentDocument delete error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression.',
            ], 500);
        }
    }

    public function markReturned(Request $request, int $id): JsonResponse
    {
        Gate::authorize('edit-document');
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string',
            ]);

            $companyId = $this->getCompanyId();

            $document = PaymentDocument::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $document->markAsReturned($validated['reason'] ?? null);

            return response()->json($document->load(['customer', 'bankRemittance']));
        } catch (\Throwable $e) {
            Log::error("PaymentDocument markReturned error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
            ], 500);
        }
    }

    public function markPaid(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $companyId = $this->getCompanyId();

            $document = PaymentDocument::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $document->markAsPaid();

            return response()->json($document->load(['customer', 'bankRemittance']));
        } catch (\Throwable $e) {
            Log::error("PaymentDocument markPaid error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
            ], 500);
        }
    }

    protected function getCompanyId(): int
    {
        return config('app.current_company_id') ?? throw new \RuntimeException('Aucune entreprise sélectionnée.');
    }
}
