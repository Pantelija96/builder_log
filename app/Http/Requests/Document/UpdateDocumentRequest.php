<?php

namespace App\Http\Requests\Document;

use App\Enums\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
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

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'type' => [
                'nullable',
                Rule::enum(DocumentType::class),
            ],

            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],

        ];
    }
}
