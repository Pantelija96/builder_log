<?php

namespace App\Http\Requests\WorkerAttendance;

use Illuminate\Foundation\Http\FormRequest;

class CreateWorkerAttendanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'worker_id' => [
                'required',
                'integer',
                'exists:workers,id',
            ],

            'started_at' => [
                'required',
                'date',
            ],

            'finished_at' => [
                'nullable',
                'date',
                'after_or_equal:started_at',
            ],

            'advance_payment' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }
}
