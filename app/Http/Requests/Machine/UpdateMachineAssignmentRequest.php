<?php

namespace App\Http\Requests\Machine;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMachineAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'started_at' => [
                'required',
                'date',
            ],

            'finished_at' => [
                'nullable',
                'date',
                'after:started_at',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
