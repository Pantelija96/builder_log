<?php

namespace App\Http\Requests\Subcontractor;

use Illuminate\Foundation\Http\FormRequest;

class DeleteSubcontractorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }
}
