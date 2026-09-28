<?php

namespace App\Http\Requests\Task;

use App\Enums\WorkerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'due_date' => [
                'nullable',
                'date',
            ],

            'worker_id' => [
                'nullable',
                Rule::exists('workers', 'id'),
            ],

            'target_role' => [
                'nullable',
                Rule::in([
                    WorkerRole::SITE_MANAGER->value,
                    WorkerRole::OPERATOR->value,
                    WorkerRole::DRIVER->value,
                ]),
            ],

            'construction_site_id' => [
                'nullable',
                Rule::exists('construction_sites', 'id'),
            ],

            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $targets = collect([
                $this->input('worker_id'),
                $this->input('target_role'),
                $this->input('construction_site_id'),
            ])->filter(
                fn ($value) => $value !== null && $value !== ''
            );

            if ($targets->count() !== 1) {
                $validator->errors()->add(
                    'target',
                    'Task must be assigned to exactly one worker, role, or construction site.'
                );
            }
        });
    }
}
