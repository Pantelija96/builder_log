<?php

namespace App\Http\Requests\WorkerAttendance;

use Illuminate\Foundation\Http\FormRequest;

class GetAbsentWorkersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => [
                'nullable',
                'date',
                'required_without_all:date_from,date_to',
            ],

            'date_from' => [
                'nullable',
                'date',
                'required_with:date_to',
            ],

            'date_to' => [
                'nullable',
                'date',
                'required_with:date_from',
                'after_or_equal:date_from',
            ],
        ];
    }
}
