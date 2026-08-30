<?php

namespace App\Http\Requests\SubcontractorLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetSubcontractorLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'daily_log_id' => [
                'nullable',
                'integer',
                Rule::exists('daily_logs', 'id'),
            ],

            'subcontractor_id' => [
                'nullable',
                'integer',
                Rule::exists('subcontractors', 'id'),
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'sort' => [
                'nullable',
                'string',
            ],

            'direction' => [
                'nullable',
                'in:asc,desc',
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
