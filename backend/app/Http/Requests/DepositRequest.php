<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quote_id' => 'nullable|integer',
            'customer_id' => 'required_without:quote_id|exists:customers,id',
            'input_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'input_value' => 'required|numeric|min:0.01',
            'due_date' => 'nullable|date',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'deposit_description' => 'nullable|string|max:500',
            'payment_condition' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|string|max:255',
            'late_fee_interest' => 'nullable|string|max:255',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'intro_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'conclusion_text' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required_without' => 'Veuillez sélectionner un client ou un devis.',
            'customer_id.exists' => 'Le client sélectionné est introuvable.',
            'input_value.required' => 'Le montant est requis.',
            'input_value.numeric' => 'Le montant doit être un nombre.',
            'input_value.min' => 'Le montant doit être supérieur à zéro.',
            'tax_rate.required' => 'Le taux de TVA est requis.',
            'tax_rate.numeric' => 'Le taux de TVA doit être un nombre.',
            'tax_rate.min' => 'Le taux de TVA ne peut pas être négatif.',
            'tax_rate.max' => 'Le taux de TVA ne peut pas dépasser 100%.',
            'bank_account_id.exists' => 'Le compte bancaire sélectionné est introuvable.',
            'input_type.in' => 'Le type de montant doit être soit "percentage" soit "fixed".',
            'due_date.date' => 'La date d\'échéance doit être une date valide.',
        ];
    }
}
