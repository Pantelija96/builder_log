<?php

namespace App\Http\Requests\Get;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Enums\OwnerType;
use App\QueryFilters\MachineFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GetMachinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'company_id' => [
                'sometimes',
                'integer',
                'exists:companies,id',
            ],

            'name' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'type' => [
                'sometimes',
                new Enum(MachineType::class),
            ],

            'exclude_type' => [
                'sometimes',
                new Enum(MachineType::class),
            ],

            'status' => [
                'sometimes',
                new Enum(MachineStatus::class),
            ],

            'owner_type' => [
                'sometimes',
                new Enum(OwnerType::class),
            ],

            'owner_id' => [
                'sometimes',
                'integer',
            ],

            'sort' => [
                'sometimes',
                Rule::in(MachineFilter::SORTABLE),
            ],

            'direction' => [
                'sometimes',
                Rule::in(['asc', 'desc']),
            ],

            'offset' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'limit' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
