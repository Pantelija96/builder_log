<?php

namespace App\Http\Requests\Document;

use App\Enums\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDocumentRequest extends FormRequest
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

            'files' => [
                'required',
                'array',
                'min:1',
            ],

            'files.*' => [
                'required',
                'file',
//                'max:62000',
            ],

        ];
    }
}
