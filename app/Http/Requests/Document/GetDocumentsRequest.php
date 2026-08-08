<?php

namespace App\Http\Requests\Document;

use App\Enums\DocumentType;
use App\QueryFilters\DocumentFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetDocumentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'construction_site_id' => [
                'required',
                Rule::exists(
                    'construction_sites',
                    'id'
                ),
            ],

            'site_manager_id' => [
                'nullable',
                Rule::exists(
                    'workers',
                    'id'
                ),
            ],

            'type' => [
                'nullable',
                Rule::enum(DocumentType::class),
            ],

            'uploaded_by' => [
                'nullable',
                Rule::exists(
                    'workers',
                    'id'
                ),
            ],

            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
            ],

            'sort' => [
                'sometimes',
                'nullable',
                'string',
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
