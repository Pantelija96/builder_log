<?php

namespace App\Http\Requests\TruckLog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTruckLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_manager_started_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'site_manager_finished_at' => [
                'sometimes',
                'nullable',
                'date',
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

            'start_mileage' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'end_mileage' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
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
