<?php

namespace App\Http\Requests\WorkerPayment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWorkerPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'worker_id' => [
                'required',
                'integer',
                Rule::exists('workers', 'id'),
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'date' => [
                'required',
                'date',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
