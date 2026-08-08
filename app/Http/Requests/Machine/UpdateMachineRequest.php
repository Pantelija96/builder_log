<?php

namespace App\Http\Requests\Machine;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Enums\OwnerType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMachineRequest extends FormRequest
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
                'required',
                Rule::enum(MachineStatus::class),
            ],

            'image_path' => [
                'nullable',
                'string',
                'max:500',
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
