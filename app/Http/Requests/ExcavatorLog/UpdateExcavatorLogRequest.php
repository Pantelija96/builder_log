<?php

namespace App\Http\Requests\ExcavatorLog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExcavatorLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_manager_started_at' => [
                'nullable',
                'date',
                'required_with:site_manager_finished_at',
            ],

            'site_manager_finished_at' => [
                'nullable',
                'date',
                'required_with:site_manager_started_at',
                'after:site_manager_started_at',
            ],

            'operator_started_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'operator_finished_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:operator_started_at',
            ],

            'work_hours' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'start_work_hours' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'finish_work_hours' => [
                'sometimes',
                'nullable',
                'numeric',
                'after_or_equal:start_work_hours',
            ],

            'fuel_added' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'fuel_remaining' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'note_site_manager' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'note_operator' => [
                'sometimes',
                'nullable',
                'string',
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
