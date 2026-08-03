<?php

namespace App\Http\Requests\Get;

use App\Enums\WorkerRole;
use App\QueryFilters\WorkerFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GetWorkersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'first_name' => [
                'sometimes',
                'string',
                'max:100',
            ],

            'last_name' => [
                'sometimes',
                'string',
                'max:100',
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

            'company_id' => [
                'nullable',
                'integer',
                'exists:companies,id',
            ],

            'role' => [
                'nullable',
                new Enum(WorkerRole::class),
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'sort' => [
                'sometimes',
                Rule::in(WorkerFilter::SORTABLE),
            ],

            'direction' => [
                'nullable',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],

            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
