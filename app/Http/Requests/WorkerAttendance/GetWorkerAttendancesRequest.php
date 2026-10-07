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

            'date_created_from' => [
                'nullable',
                'date',
            ],

            'date_created_to' => [
                'nullable',
                'date',
            ],

            'construction_site_id' => [
                'nullable',
                'integer',
                'exists:construction_sites,id',
            ],

            'date' => [
                'nullable',
                'date',
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

            'role' => [
                'nullable',
                'string',
                'in:worker,operator,driver',
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
