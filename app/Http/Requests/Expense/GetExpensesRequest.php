<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetExpensesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'title' => [
                'nullable',
                'string',
                'max:150',
            ],

            'created_by' => [
                'nullable',
                'integer',
                'exists:workers,id',
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

            'min_amount' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'max_amount' => [
                'nullable',
                'numeric',
                'gte:min_amount',
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

            'construction_site_id' => [
                'nullable',
                Rule::exists('construction_sites', 'id'),
            ],

            'site_manager_id' => [
                'nullable',
                Rule::exists('workers', 'id'),
            ],
        ];
    }
}
