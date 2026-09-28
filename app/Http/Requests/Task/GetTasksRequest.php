<?php

namespace App\Http\Requests\Task;

use App\Enums\WorkerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetTasksRequest extends FormRequest
{
    public function rules(): array
    {
        return [

            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'title' => [
                'nullable',
                'string',
                'max:255',
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

            'created_by' => [
                'nullable',
                Rule::exists('workers', 'id'),
            ],

            'completed' => [
                'nullable',
                'boolean',
            ],

            'read' => [
                'nullable',
                'boolean',
            ],

            'due_date_from' => [
                'nullable',
                'date',
            ],

            'due_date_to' => [
                'nullable',
                'date',
                'after_or_equal:due_date_from',
            ],

            'date_created_from' => [
                'nullable',
                'date',
            ],

            'date_created_to' => [
                'nullable',
                'date',
            ],

            'sort' => [
                'nullable',
                Rule::in([
                    'id',
                    'title',
                    'due_date',
                    'created_at',
                ]),
            ],

            'direction' => [
                'nullable',
                Rule::in([
                    'asc',
                    'desc',
                ]),
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
