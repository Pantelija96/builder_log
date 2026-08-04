<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetNotesRequest extends FormRequest
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
                Rule::exists('daily_logs', 'id'),
            ],

            'notify_admin' => [
                'nullable',
                'boolean',
            ],

            'created_by' => [
                'nullable',
                Rule::exists('workers', 'id'),
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
