<?php

namespace App\Http\Requests\Machine;

use Illuminate\Foundation\Http\FormRequest;

class CreateMachineAssignmentForOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'construction_site_id' => [
                'required',
                'integer',
                'exists:construction_sites,id',
            ],

            'machine_id' => [
                'required',
                'integer',
                'exists:machines,id',
            ],
        ];
    }
}
