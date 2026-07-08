<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'customer_id' => 'required|exists:customers,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'due_date' => 'required|date',
            'type' => ['required', Rule::in(['STANDARD', 'DEPOSIT'])],
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
        ];

        if ($this->input('type') === 'STANDARD') {
            $rules['items'] = 'required|array|min:1';
            $rules['items.*.product_id'] = 'nullable|exists:products,id';
            $rules['items.*.product_type'] = 'nullable|string|max:255';
            $rules['items.*.designation'] = 'required|string|max:255';
            $rules['items.*.quantity'] = 'required|numeric|min:0.01';
            $rules['items.*.unit_price'] = 'required|numeric|min:0';
            $rules['items.*.tax_rate'] = 'required|numeric|min:0|max:100';
            $rules['items.*.discount_type'] = ['nullable', Rule::in(['percentage', 'fixed'])];
            $rules['items.*.discount_value'] = 'nullable|numeric|min:0';
        } else {
            // DEPOSIT
            $rules['deposit_input_type'] = ['required', Rule::in(['percentage', 'fixed'])];
            $rules['deposit_input_value'] = 'required|numeric|min:0';
            $rules['tax_rate'] = 'required|numeric|min:0|max:100';
            $rules['deposit_description'] = 'nullable|string|max:255';
        }

        return $rules;
    }
}