<?php

namespace App\Http\Requests\CashAdvance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetCashAdvancesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'site_manager_id' => [
                'nullable',
                Rule::exists('workers', 'id'),
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
