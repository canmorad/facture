<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditNoteRequest;
use App\Services\CreditNoteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class CreditNoteController extends Controller
{
    public function __construct(protected CreditNoteService $creditNoteService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $perPage = (int) $request->input('per_page', 10);
            $filters = [
                'status' => $request->input('status'),
                'type' => $request->input('type'),
                'search' => $request->input('search'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'customer_id' => $request->input('customer_id'),
            ];

            $creditNotes = $this->creditNoteService->getPaginated($filters, $perPage);
            return response()->json($creditNotes);
        } catch (\Throwable $e) {
            Log::error('CreditNote index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des avoirs.',
            ], 500);
        }
    }

    public function create(): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $data = $this->creditNoteService->getCreationData();
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('CreditNote create error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors du chargement des données.',
            ], 500);
        }
    }

    public function store(CreditNoteRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        try {
            $document = $this->creditNoteService->createFromInvoice(
                $request->input('invoice_id'),
                $request->validated()
            );
            return response()->json($document, 201);
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
            Log::error('CreditNote store error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de l\'avoir.',
            ], 500);
        }
    }

    public function finalize(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $document = $this->creditNoteService->finalize($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("CreditNote finalize error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
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
            $document = $this->creditNoteService->send($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("CreditNote send error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi.',
            ], 500);
        }
    }

    public function apply(int $id): JsonResponse
    {
        Gate::authorize('finalize-document');
        try {
            $document = $this->creditNoteService->apply($id);
            return response()->json($document);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error("CreditNote apply error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'application.',
            ], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $companyId = $this->getCompanyId();
            $document = \App\Models\Document::where('id', $id)
                ->where('company_id', $companyId)
                ->where('documentable_type', \App\Models\CreditNote::class)
                ->with(['customer', 'items', 'documentable', 'parent'])
                ->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Avoir introuvable.',
                ], 404);
            }

            $actions = $this->creditNoteService->getAvailableActions($document->documentable_id);

            return response()->json([
                'document' => $document,
                'available_actions' => $actions,
            ]);
        } catch (\Throwable $e) {
            Log::error("CreditNote show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de charger les détails de l\'avoir.',
            ], 500);
        }
    }

    public function actions(int $id): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $actions = $this->creditNoteService->getAvailableActions($id);
            return response()->json(['actions' => $actions]);
        } catch (\Throwable $e) {
            Log::error("CreditNote actions error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }
}
