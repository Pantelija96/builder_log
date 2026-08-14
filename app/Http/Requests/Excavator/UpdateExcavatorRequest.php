<?php

namespace App\Http\Requests\Excavator;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExcavatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'initial_work_hours' => [
                'required',
                'numeric',
                'min:0',
            ],

            'total_work_hours' => [
                'nullable',
                'sometimes',
                'numeric',
                'min:0',
            ],

            'reason' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
