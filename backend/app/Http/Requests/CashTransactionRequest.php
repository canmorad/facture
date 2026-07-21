<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'cash_register_id' => ['required', 'exists:cash_registers,id'],
            'type' => ['required', 'in:in,out,transfer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'transactionable_type' => ['nullable', 'string'],
            'transactionable_id' => ['nullable', 'integer'],
            'from_cash_register_id' => ['required_if:type,transfer', 'exists:cash_registers,id'],
            'to_cash_register_id' => ['required_if:type,transfer', 'exists:cash_registers,id'],
            'transaction_date' => ['nullable', 'date'],
            'is_verified' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'cash_register_id.required' => 'La caisse est requise.',
            'cash_register_id.exists' => 'La caisse sélectionnée n\'existe pas.',
            'type.required' => 'Le type de transaction est requis.',
            'type.in' => 'Le type doit être: in, out, ou transfer.',
            'amount.required' => 'Le montant est requis.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'amount.min' => 'Le montant doit être supérieur à 0.',
            'description.required' => 'La description est requise.',
            'from_cash_register_id.required_if' => 'La caisse source est requise pour un transfert.',
            'to_cash_register_id.required_if' => 'La caisse destination est requise pour un transfert.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('type') === 'transfer') {
            $this->merge([
                'from_cash_register_id' => $this->input('cash_register_id'),
            ]);
        }
    }
}
