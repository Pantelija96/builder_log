<?php

namespace App\Http\Requests\Machine;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Enums\OwnerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'type' => [
                'required',
                Rule::enum(MachineType::class),
            ],

            'owner_type' => [
                'required',
                Rule::enum(OwnerType::class),
            ],

            'owner_id' => [
                'required',
                'integer',
            ],

            'status' => [
                'nullable',
                Rule::enum(MachineStatus::class),
            ],

            'image_path' => [
                'nullable',
                'string',
                'max:500',
            ],

            'license_plate' => [
                'nullable',
                'string',
                'max:30',
                'unique:machines,license_plate',
            ],

            'initial_work_hours' => [
                'nullable',
                'numeric',
                'min:0',
                'required_if:type,' . MachineType::EXCAVATOR->value,
            ],

            'initial_mileage' => [
                'nullable',
                'numeric',
                'min:0',
                'required_if:type,' . MachineType::TRUCK->value,
            ],
        ];
    }
}
