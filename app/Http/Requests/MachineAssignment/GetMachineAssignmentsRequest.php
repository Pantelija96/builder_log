<?php

namespace App\Http\Requests\MachineAssignment;

use Illuminate\Foundation\Http\FormRequest;

class GetMachineAssignmentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date',
            ],

            'construction_site_id' => [
                'sometimes',
                'integer',
                'exists:construction_sites,id',
            ],

            'site_manager_id' => [
                'sometimes',
                'integer',
                'exists:workers,id',
            ],

            'worker_id' => [
                'sometimes',
                'integer',
                'exists:workers,id',
            ],

            'sort' => [
                'nullable',
                'string',
            ],

            'direction' => [
                'nullable',
                'in:asc,desc',
            ],

            'offset' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
