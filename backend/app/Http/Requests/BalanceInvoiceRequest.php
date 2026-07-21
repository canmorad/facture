<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BalanceInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quote_id' => 'required|integer|exists:quotes,id',
            'input_type' => ['nullable', Rule::in(['percentage', 'fixed', 'full'])],
            'input_value' => 'nullable|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'balance_description' => 'nullable|string|max:500',
            'payment_condition' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|string|max:255',
            'late_fee_interest' => 'nullable|string|max:255',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'intro_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'conclusion_text' => 'nullable|string',
            'status' => ['nullable', Rule::in(['DRAFT', 'FINALIZED'])],
        ];
    }

    public function messages(): array
    {
        return [
            'quote_id.required' => 'Le devis source est requis.',
            'quote_id.exists' => 'Le devis sélectionné est introuvable.',
            'tax_rate.required' => 'Le taux de TVA est requis.',
            'tax_rate.numeric' => 'Le taux de TVA doit être un nombre.',
            'tax_rate.min' => 'Le taux de TVA ne peut pas être négatif.',
            'tax_rate.max' => 'Le taux de TVA ne peut pas dépasser 100%.',
            'input_value.numeric' => 'Le montant doit être un nombre.',
            'input_value.min' => 'Le montant ne peut pas être négatif.',
        ];
    }
}
