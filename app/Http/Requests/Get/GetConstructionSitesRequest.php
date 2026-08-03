<?php

namespace App\Http\Requests\Get;

use App\Enums\ConstructionSiteStatus;
use App\QueryFilters\ConstructionSiteFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GetConstructionSitesRequest extends FormRequest
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

            'company_id' => [
                'sometimes',
                'integer',
                'exists:companies,id',
            ],

            'name' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'address' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'status' => [
                'sometimes',
                new Enum(ConstructionSiteStatus::class),
            ],

            'sort' => [
                'sometimes',
                Rule::in(ConstructionSiteFilter::SORTABLE),
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
