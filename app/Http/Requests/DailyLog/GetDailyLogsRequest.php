<?php

namespace App\Http\Requests\DailyLog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetDailyLogsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'company_id' => [
                'sometimes',
                'integer',
                'exists:companies,id',
            ],

            'construction_site_id' => [
                'sometimes',
                'integer',
                'exists:construction_sites,id',
            ],

            'site_manager_id' => [
                'sometimes',
                'integer',
                'exists:workers,id',
            ],

            'date' => [
                'sometimes',
                'date',
            ],

            'is_locked' => [
                'sometimes',
                'boolean',
            ],

            'sort' => [
                'sometimes',
                'string',
                Rule::in([
                    'date',
                    'created_at',
                    'updated_at',
                ]),
            ],

            'direction' => [
                'sometimes',
                Rule::in(['asc', 'desc']),
            ],

            'offset' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'limit' => [
                'sometimes',
                'integer',
                'between:1,100',
            ],
        ];
    }
}
