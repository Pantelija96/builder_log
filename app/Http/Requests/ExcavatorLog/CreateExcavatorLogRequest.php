<?php

namespace App\Http\Requests\ExcavatorLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateExcavatorLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id' => [
                'required',
                'integer',
                'exists:machines,id',
            ],

            'worker_id' => [
                'required',
                'integer',
                'exists:workers,id',
            ],

            'site_manager_started_at' => [
                'nullable',
                'date',
                'required_with:site_manager_finished_at',
            ],

            'site_manager_finished_at' => [
                'nullable',
                'date',
                'required_with:site_manager_started_at',
                'after_or_equal:site_manager_started_at',
            ],

            'note_site_manager' => [
                'nullable',
                'string',
            ],
        ];
    }
}
