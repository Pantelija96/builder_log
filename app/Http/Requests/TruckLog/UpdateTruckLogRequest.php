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
                'nullable',
                'date',
            ],

            'site_manager_finished_at' => [
                'nullable',
                'date',
                'after_or_equal:site_manager_started_at',
            ],

            'operator_started_at' => [
                'nullable',
                'date',
            ],

            'operator_finished_at' => [
                'nullable',
                'date',
                'after_or_equal:operator_started_at',
            ],

            'start_mileage' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'end_mileage' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'fuel_added' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'fuel_remaining' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'note' => [
                'nullable',
                'string',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
