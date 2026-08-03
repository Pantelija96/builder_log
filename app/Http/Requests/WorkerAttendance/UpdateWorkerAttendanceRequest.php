<?php

namespace App\Http\Requests\WorkerAttendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkerAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'started_at' => [
                'sometimes',
                'date',
            ],

            'finished_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:started_at',
            ],

            'advance_payment' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }
}
