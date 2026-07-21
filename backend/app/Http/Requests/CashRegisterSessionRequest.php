<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashRegisterSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isClosing = $this->has('actual_closing_balance');

        return [
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'actual_closing_balance' => ['required_if:actual_closing_balance,null', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'opening_balance.numeric' => 'Le solde d\'ouverture doit être un nombre.',
            'opening_balance.min' => 'Le solde d\'ouverture ne peut pas être négatif.',
            'actual_closing_balance.required' => 'Le solde de clôture est requis.',
            'actual_closing_balance.numeric' => 'Le solde de clôture doit être un nombre.',
            'actual_closing_balance.min' => 'Le solde de clôture ne peut pas être négatif.',
        ];
    }
}
