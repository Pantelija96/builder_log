<?php

namespace App\Http\Requests\TruckLog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateTruckLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id' => [
                'required',
                'integer',
                'exists:machines,id',
            ],

            'worker_id' => [
                'nullable',
                'integer',
                'exists:workers,id',
            ],

            'date' => [
                'required',
                'date',
            ],
        ];
    }
}
