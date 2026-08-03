<?php

namespace App\Http\Requests\Get;

use App\QueryFilters\CompanyFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetCompaniesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],

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

            'address' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'sort' => [
                'sometimes',
                Rule::in(CompanyFilter::SORTABLE),
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
