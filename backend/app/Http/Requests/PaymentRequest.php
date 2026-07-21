<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentMode = $this->input('payment_mode');

        return [
            'document_id' => 'required|integer|exists:documents,id',
            'payment_mode' => 'required|in:espece,cheque,lcn,virement,carte',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'cash_register_id' => 'nullable|integer|exists:cash_registers,id',
            // Required for cheques and LCN
            'document_number' => 'required_if:payment_mode,cheque,lcn|string|max:255',
            'due_date' => 'required_if:payment_mode,cheque,lcn|date',
            'drawer_name' => 'nullable|string|max:255',
            'drawer_bank' => 'nullable|string|max:255',
            'drawer_account' => 'nullable|string|max:255',
            'drawer_address' => 'nullable|string',
            'beneficiary_name' => 'nullable|string|max:255',
            'document_notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'document_id.required' => 'Le document est requis.',
            'document_id.exists' => 'Le document spécifié n\'existe pas.',
            'payment_mode.required' => 'Le mode de paiement est requis.',
            'payment_mode.in' => 'Le mode de paiement doit être: espece, cheque, lcn, virement ou carte.',
            'amount.required' => 'Le montant est requis.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'amount.min' => 'Le montant doit être supérieur à zéro.',
            'document_number.required_if' => 'Le numéro de document est requis pour les chèques et LCN.',
            'due_date.required_if' => 'La date d\'échéance est requise pour les chèques et LCN.',
            'cash_register_id.exists' => 'La caisse spécifiée n\'existe pas.',
        ];
    }
}
