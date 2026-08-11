<?php

namespace App\Http\Requests\TruckLog;

use Illuminate\Foundation\Http\FormRequest;

class GetTruckLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id' => [
                'nullable',
                'integer',
                'exists:machines,id',
            ],

            'worker_id' => [
                'nullable',
                'integer',
                'exists:workers,id',
            ],

            'date' => [
                'nullable',
                'date',
            ],

            'search' => [
                'nullable',
                'string',
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
            ],
        ];
    }
}
