<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
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
        ];
    }
}
