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
            'quote_id' => 'required|exists:quotes,id',
            'input_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'input_value' => 'required|numeric|min:1',
            'due_date' => 'required|date',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'deposit_description' => 'nullable|string|max:255',
            'payment_condition' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|string|max:255',
            'late_fee_interest' => 'nullable|string|max:255',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'intro_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'conclusion_text' => 'nullable|string',
        ];
    }
}