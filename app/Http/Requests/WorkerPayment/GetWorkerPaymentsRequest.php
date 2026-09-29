<?php

namespace App\Http\Requests\WorkerPayment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetWorkerPaymentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'worker_id' => [
                'nullable',
                'integer',
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

            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],

            'offset' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }
}
