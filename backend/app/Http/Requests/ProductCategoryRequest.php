<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        $companyId = $this->route('company') ?? $this->user()->current_company_id;

        return [
            'name'        => 'required|string|max:255|unique:product_categories,name,NULL,id,company_id,' . $companyId,
            'is_default'  => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
            'description' => 'nullable|string|max:1000',
        ];
    }
}