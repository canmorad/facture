<?php

namespace App\Http\Controllers;

use App\Models\DocumentSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class DocumentSettingController extends Controller
{
    protected function getDefaultStructure($type)
    {
        return [
            'document_type' => $type,
            'hide_signature_block' => false,
            'show_username_pdf' => true,
            'intro_text' => '',
            'conclusion_text' => '',
            'footer_text' => '',
            'terms' => '',
            'notes' => '',
        ];
    }

    public function show($type)
    {
        try {
            $companyId = $this->getCompanyId();
            $setting = DocumentSetting::where('company_id', $companyId)
                ->where('document_type', $type)
                ->first();

            if (!$setting) {
                return response()->json($this->getDefaultStructure($type));
            }

            return response()->json($setting);
        } catch (\Throwable $e) {
            Log::error("DocumentSetting show error type {$type}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    public function storeOrUpdate(Request $request)
    {
        try {
            $companyId = $this->getCompanyId();
            $validated = $request->validate([
                'document_type' => ['required', Rule::in([
                    'QUOTE', 'INVOICE', 'PROFORMA', 'PURCHASE_ORDER',
                    'DELIVERY_NOTE', 'CREDIT_NOTE',
                    'DEPOSIT_INVOICE', 'DEPOSIT_CREDIT_NOTE'
                ])],
                'hide_signature_block' => 'sometimes|boolean',
                'show_username_pdf' => 'sometimes|boolean',
                'intro_text' => 'nullable|string',
                'conclusion_text' => 'nullable|string',
                'footer_text' => 'nullable|string',
                'terms' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $setting = DocumentSetting::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'document_type' => $validated['document_type'],
                ],
                [
                    'hide_signature_block' => $validated['hide_signature_block'] ?? false,
                    'show_username_pdf' => $validated['show_username_pdf'] ?? true,
                    'intro_text' => $validated['intro_text'] ?? null,
                    'conclusion_text' => $validated['conclusion_text'] ?? null,
                    'footer_text' => $validated['footer_text'] ?? null,
                    'terms' => $validated['terms'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            return response()->json($setting);
        } catch (\Throwable $e) {
            Log::error('DocumentSetting storeOrUpdate error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la sauvegarde.'], 500);
        }
    }
}