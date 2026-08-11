<?php

namespace App\Http\Requests\ExcavatorLog;

use Illuminate\Foundation\Http\FormRequest;

class CreateExcavatorLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_assignment_id' => [
                'required',
                'integer',
                'exists:machine_assignments,id',
            ],

            'worker_id' => [
                'nullable',
                'integer',
                'exists:workers,id',
            ],
        ];
    }
}
