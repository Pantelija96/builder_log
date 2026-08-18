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

            'construction_site_id' => [
                'nullable',
                Rule::exists('construction_sites', 'id'),
            ],

            'site_manager_id' => [
                'nullable',
                Rule::exists('workers', 'id'),
            ],

            'notify_admin' => [
                'nullable',
                'boolean',
            ],

            'created_by' => [
                'nullable',
                Rule::exists('workers', 'id'),
            ],

            'date_created_from' => [
                'nullable',
                'date',
            ],

            'date_created_to' => [
                'nullable',
                'date',
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
