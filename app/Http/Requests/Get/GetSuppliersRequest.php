<?php

namespace App\Http\Requests\Get;

use App\QueryFilters\SupplierFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetSuppliersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'name' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'pib' => [
                'sometimes',
                'string',
                'max:20',
            ],

            'email' => [
                'sometimes',
                'email',
            ],

            'phone' => [
                'sometimes',
                'string',
                'max:30',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'sort' => [
                'sometimes',
                Rule::in(SupplierFilter::SORTABLE),
            ],

            'direction' => [
                'sometimes',
                Rule::in(['asc', 'desc']),
            ],

            'offset' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'limit' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
