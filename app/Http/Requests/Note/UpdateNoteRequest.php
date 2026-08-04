<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
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

            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }
}
