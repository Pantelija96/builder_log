<?php

namespace App\Http\Requests\DeliveryNote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'attachments' => [
                'sometimes',
                'array',
            ],

            'attachments.*' => [
                'file',
                'max:10240',
            ],

            'delete_attachments' => [
                'sometimes',
                'array',
            ],

            'delete_attachments.*' => [
                'integer',
            ],

            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }
}
