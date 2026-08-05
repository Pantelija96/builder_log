<?php

namespace App\Http\Requests\Task;

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

            'site_manager_id' => [
                'nullable',
                Rule::exists('workers', 'id'),
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
