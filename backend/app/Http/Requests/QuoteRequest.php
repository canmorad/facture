<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
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
            'date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:date',
            'payment_condition' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|string|max:255',
            'late_fee_interest' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_type' => 'nullable|string|max:255',
            'items.*.designation' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'required|numeric|min:0|max:100',
            'items.*.discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'global_discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'global_discount_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'intro_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'conclusion_text' => 'nullable|string',
        ];
    }
}