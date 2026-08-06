<?php

namespace App\Http\Requests\CashAdvance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCashAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'site_manager_id' => [
                'required',
                'exists:workers,id',
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

            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }
}
