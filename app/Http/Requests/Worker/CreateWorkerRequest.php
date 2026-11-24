<?php

namespace App\Http\Requests\Worker;

use App\Enums\WorkerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /**
             * Zero is unprioritized. Lower positive values appear first, before all zeros.
             *
             * @default 0
             */
            'display_order' => ['sometimes', 'integer:strict', 'min:0', 'max:2147483647'],

            'first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'role' => [
                'required',
                Rule::enum(WorkerRole::class),
            ],

            'hourly_rate' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'username' => [
                'required',
                'string',
                'max:100',
                'unique:workers,username',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'construction_site_ids' => [
                'nullable',
                'array',
            ],

            'construction_site_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('construction_sites', 'id'),
            ],
        ];
    }
}
