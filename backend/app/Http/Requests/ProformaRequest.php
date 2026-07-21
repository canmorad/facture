<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProformaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'validity_date' => 'nullable|date|after:today',
            'payment_condition' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|string|max:255',
            'late_fee_interest' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'intro_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'conclusion_text' => 'nullable|string',
            'global_discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'global_discount_value' => 'nullable|numeric|min:0',
            'parent_document_id' => 'nullable|integer|exists:documents,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_type' => 'nullable|string|max:255',
            'items.*.designation' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'required|numeric|min:0|max:100',
            'items.*.discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'items.*.discount_value' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Le client est obligatoire.',
            'items.required' => 'Au moins un article est requis.',
            'items.min' => 'Au moins un article est requis.',
            'items.*.designation.required' => 'La désignation de l\'article est obligatoire.',
            'items.*.quantity.required' => 'La quantité est obligatoire.',
            'items.*.quantity.min' => 'La quantité doit être supérieure à 0.',
            'items.*.unit_price.required' => 'Le prix unitaire est obligatoire.',
            'items.*.unit_price.min' => 'Le prix unitaire doit être supérieur ou égal à 0.',
            'items.*.tax_rate.required' => 'Le taux de TVA est obligatoire.',
            'validity_date.after' => 'La date de validité doit être ultérieure à aujourd\'hui.',
        ];
    }
}
