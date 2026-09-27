<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdminExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'construction_site_id' => [
                'required',
                'integer',
                Rule::exists('construction_sites', 'id'),
            ],

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

            'date' => [
                'required',
                'date',
            ],

            'attachments' => [
                'nullable',
                'array',
            ],

            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx',
                'max:1024000',
            ],
        ];
    }
}
