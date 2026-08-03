<?php

namespace App\Http\Requests\SubcontractorLog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubcontractorLogRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_count' => [
                'required',
                'integer',
                'min:0',
            ],

            'started_at' => [
                'nullable',
                'date',
            ],

            'finished_at' => [
                'nullable',
                'date',
                'after:started_at',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }

}
