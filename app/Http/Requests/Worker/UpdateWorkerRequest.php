<?php

namespace App\Http\Requests\Worker;

use App\Enums\WorkerRole;
use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Worker $worker */
        $worker = $this->route('worker');

        return [
            'first_name' => [
                'sometimes',
                'string',
                'max:100',
            ],

            'last_name' => [
                'sometimes',
                'string',
                'max:100',
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'role' => [
                'sometimes',
                Rule::enum(WorkerRole::class),
            ],

            'hourly_rate' => [
                'sometimes',
                'nullable',
                'numeric',
                'gte:0',
            ],

            'username' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('workers', 'username')->ignore($worker?->id),
            ],

            'password' => [
                'sometimes',
                'string',
                'min:8',
            ],

            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'construction_site_ids' => [
                'sometimes',
                'array',
            ],

            'construction_site_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('construction_sites', 'id'),
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
