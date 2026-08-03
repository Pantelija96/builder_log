<?php

namespace App\Http\Requests\SubcontractorLog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSubcontractorLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subcontractor_id' => [
                'required',
                'integer',
                Rule::exists('subcontractors', 'id'),
            ],

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
                'sometimes',
                'nullable',
                'date',
                'after:started_at',
            ],

            'note' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
