<?php
// app/Http/Requests/LateFeeInterestRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LateFeeInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->route('company') ?? $this->user()?->current_company_id;
        $ignoreId = $this->route('late_fee_interest')?->id ?? null;

        return [
            'label' => [
                'required',
                'string',
                'max:255',
                Rule::unique('late_fee_interests')
                    ->where('company_id', $companyId)
                    ->ignore($ignoreId),
            ],
            'is_active'  => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->route('company') && $this->user()) {
            $this->merge([
                'company_id' => $this->user()->current_company_id,
            ]);
        }
    }
}