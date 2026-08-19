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

            'reason' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
