<?php

namespace App\Http\Requests\TruckLog;

use Illuminate\Foundation\Http\FormRequest;

class CreateTruckLogRequest extends FormRequest
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
            ],

            'site_manager_finished_at' => [
                'nullable',
                'date',
            ],

            'advance_payment' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'note_site_manager' => [
                'nullable',
                'string',
            ],
        ];
    }
}
