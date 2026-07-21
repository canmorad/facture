<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\NumberingSerie;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class NumberingSerieController extends Controller
{
    public function show()
    {
        try {
            $companyId = $this->getCompanyId();
            $serie = NumberingSerie::where('company_id', $companyId)->first();
            $hasDocuments = $this->checkIfAnyDocumentsExist($companyId);

            if ($serie) {
                return response()->json([
                    'exists' => true,
                    'data' => $serie,
                    'has_documents' => $hasDocuments,
                ], 200);
            }

            return response()->json([
                'exists' => false,
                'data' => null,
                'has_documents' => $hasDocuments,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('NumberingSerie show error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $companyId = $this->getCompanyId();

            $validated = $request->validate([
                'format' => 'required|string|max:255',
                'min_size' => 'required|integer|min:1|max:20',
                'reset_period' => ['required', Rule::in(['never', 'yearly', 'monthly'])],
                'start_from_invoice' => 'required|integer|min:0',
                'start_from_quote' => 'required|integer|min:0',
                'start_from_credit_note' => 'required|integer|min:0',
                'start_from_deposit_invoice' => 'required|integer|min:0',
                'start_from_deposit_credit_note' => 'required|integer|min:0',
                'start_from_balance_invoice' => 'required|integer|min:0',
                'start_from_delivery_note' => 'required|integer|min:0',
                'start_from_purchase_order' => 'required|integer|min:0',
                'start_from_bank_remittance' => 'required|integer|min:0',
            ]);

            $validated['company_id'] = $companyId;

            $startFields = [
                'start_from_invoice', 'start_from_quote', 'start_from_credit_note',
                'start_from_deposit_invoice', 'start_from_deposit_credit_note',
                'start_from_balance_invoice', 'start_from_delivery_note', 'start_from_purchase_order',
                'start_from_bank_remittance',
            ];

            foreach ($startFields as $field) {
                $currentField = str_replace('start_from_', 'current_', $field);
                $validated[$currentField] = $validated[$field];
            }

            $serie = NumberingSerie::create($validated);
            return response()->json($serie, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('NumberingSerie store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de l\'enregistrement.'], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $companyId = $this->getCompanyId();

            $serie = NumberingSerie::where('company_id', $companyId)->first();
            if (!$serie) {
                return response()->json(['success' => false, 'message' => 'Configuration introuvable.'], 404);
            }

            $hasDocuments = $this->checkIfAnyDocumentsExist($companyId);

            if ($hasDocuments && $request->hasAny(['format', 'min_size', 'reset_period'])) {
                return response()->json(['success' => false, 'message' => 'Impossible de modifier le format, la taille ou la période de réinitialisation car des documents ont déjà été créés.'], 422);
            }

            $validated = $request->validate([
                'format' => 'sometimes|string|max:255',
                'min_size' => 'sometimes|integer|min:1|max:20',
                'reset_period' => ['sometimes', Rule::in(['never', 'yearly', 'monthly'])],
                'start_from_invoice' => 'sometimes|integer|min:0',
                'start_from_quote' => 'sometimes|integer|min:0',
                'start_from_credit_note' => 'sometimes|integer|min:0',
                'start_from_deposit_invoice' => 'sometimes|integer|min:0',
                'start_from_deposit_credit_note' => 'sometimes|integer|min:0',
                'start_from_balance_invoice' => 'sometimes|integer|min:0',
                'start_from_delivery_note' => 'sometimes|integer|min:0',
                'start_from_purchase_order' => 'sometimes|integer|min:0',
                'start_from_bank_remittance' => 'sometimes|integer|min:0',
            ]);

            $startFields = [
                'start_from_invoice', 'start_from_quote', 'start_from_credit_note',
                'start_from_deposit_invoice', 'start_from_deposit_credit_note',
                'start_from_balance_invoice', 'start_from_delivery_note', 'start_from_purchase_order',
                'start_from_bank_remittance',
            ];

            foreach ($startFields as $field) {
                if (array_key_exists($field, $validated)) {
                    $currentField = str_replace('start_from_', 'current_', $field);
                    if ($serie->{$field} != $serie->{$currentField}) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            $field => "Impossible de modifier la valeur de départ car le compteur actuel a déjà évolué."
                        ]);
                    }
                    $validated[$currentField] = $validated[$field];
                }
            }

            $serie->update($validated);
            return response()->json($serie);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('NumberingSerie update error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.'], 500);
        }
    }

    private function checkIfAnyDocumentsExist(int $companyId): bool
    {
        return Document::where('company_id', $companyId)->exists();
    }
}