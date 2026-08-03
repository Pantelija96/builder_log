<?php

namespace App\Http\Requests\WorkerAttendance;

use Illuminate\Foundation\Http\FormRequest;

class GetWorkerAttendancesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'worker_id' => [
                'nullable',
                'integer',
                'exists:workers,id',
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
