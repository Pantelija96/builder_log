<?php

namespace App\Http\Requests\MachineAssignment;

use Illuminate\Foundation\Http\FormRequest;

class GetCurrentMachineAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'worker_id' => [
                'sometimes',
                'integer',
                'exists:workers,id',
            ],
        ];
    }
}
