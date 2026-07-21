<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('cash_registers', 'code')->ignore($this->route('id'))->whereNull('deleted_at'),
            ],
            'type' => ['required', 'in:cash,bank,vault,petty_cash'],
            'currency' => ['nullable', 'string', 'max:10'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la caisse est requis.',
            'code.unique' => 'Ce code est déjà utilisé.',
            'type.required' => 'Le type de caisse est requis.',
            'type.in' => 'Le type de caisse doit être: cash, bank, vault, ou petty_cash.',
            'opening_balance.numeric' => 'Le solde d\'ouverture doit être un nombre.',
            'opening_balance.min' => 'Le solde d\'ouverture ne peut pas être négatif.',
        ];
    }
}
