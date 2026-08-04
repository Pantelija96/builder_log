<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;

class CreateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => [
                'required',
                'string',
                'max:5000',
            ],

            'notify_admin' => [
                'required',
                'boolean',
            ],

            'attachments' => [
                'nullable',
                'array',
            ],

            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx',
                'max:10240',
            ],
        ];
    }
}
