<?php

namespace App\Http\Requests\Machine;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateMachineAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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

            'started_at' => [
                'required',
                'date',
            ],

            'finished_at' => [
                'nullable',
                'date',
                'after:started_at',
            ],
        ];
    }
}
