<?php

namespace App\Http\Requests\Machine;

use App\Enums\MachineStatus;
use App\Enums\OwnerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMachineRequest extends FormRequest
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

            'owner_type' => [
                'required',
                Rule::enum(OwnerType::class),
            ],

            'owner_id' => [
                'required',
                'integer',
            ],

            'status' => [
                'required',
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
                Rule::unique('machines', 'license_plate')->ignore($this->route('machine')),
            ],

            'reason' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
