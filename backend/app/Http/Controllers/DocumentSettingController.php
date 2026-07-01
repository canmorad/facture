<?php

namespace App\Http\Controllers;

use App\Models\DocumentSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentSettingController extends Controller
{
    protected function getCompanyId(Request $request)
    {
        return $request->input('company_id') ?? auth()->user()->currentCompanyId;
    }

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

    public function show(Request $request, $type)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $setting = DocumentSetting::where('company_id', $companyId)
            ->where('document_type', $type)
            ->first();

        if (!$setting) {
            return response()->json($this->getDefaultStructure($type));
        }

        return response()->json($setting);
    }

    public function storeOrUpdate(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

        $validated = $request->validate([
            'document_type' => ['required', Rule::in([
                'QUOTE', 'INVOICE', 'PURCHASE_ORDER',
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

        $validated['company_id'] = $companyId;

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
    }
}