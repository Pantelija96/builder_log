<?php

namespace App\Http\Requests\ExcavatorLog;

use Illuminate\Foundation\Http\FormRequest;

class DeleteExcavatorLogRequest extends FormRequest
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
