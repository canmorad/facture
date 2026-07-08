<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'reference' => 'nullable|string|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'total_ht' => 'required|numeric|min:0',
            'total_tva' => 'required|numeric|min:0',
            'total_ttc' => 'required|numeric|min:0',
            'status' => ['sometimes', Rule::in(['unpaid', 'paid'])],
            'payment_method' => ['required', Rule::in(['virement', 'cheque', 'espece', 'carte'])],
            'notes' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'total_ttc.required' => 'Le total TTC est obligatoire.',
            'files.*.mimes' => 'Seuls les fichiers JPG, PNG et PDF sont acceptés.',
            'files.*.max' => 'Chaque fichier ne doit pas dépasser 10 Mo.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ht = (float) $this->input('total_ht', 0);
            $tva = (float) $this->input('total_tva', 0);
            $ttc = (float) $this->input('total_ttc', 0);
            $expected = round($ht + $tva, 2);
            $actual = round($ttc, 2);

            if (abs($expected - $actual) > 0.01) {
                $validator->errors()->add('total_ttc', 'Le total TTC (' . number_format($actual, 2) . ') ne correspond pas à total HT + TVA (' . number_format($expected, 2) . ').');
            }
        });
    }
}