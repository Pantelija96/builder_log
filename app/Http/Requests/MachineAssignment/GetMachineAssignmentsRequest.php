<?php

namespace App\Http\Requests\MachineAssignment;

use App\Enums\MachineType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetMachineAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => [
                'sometimes',
                'date',
            ],

            'date_from' => [
                'sometimes',
                'date',
            ],

            'date_to' => [
                'sometimes',
                'date',
                'after_or_equal:date_from',
            ],

            'machine_id' => [
                'sometimes',
                'integer',
                'exists:machines,id',
            ],

            'machine_type' => [
                'sometimes',
                Rule::enum(MachineType::class),
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

            'created_by' => [
                'sometimes',
                'integer',
                'exists:workers,id',
            ],

            'deleted' => [
                'sometimes',
                'boolean',
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
