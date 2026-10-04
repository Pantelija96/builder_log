<?php

namespace App\Http\Requests\TruckLog;

use Illuminate\Foundation\Http\FormRequest;

class CreateTruckLogForDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id' => [
                'required',
                'integer',
                'exists:machines,id',
            ],

            'construction_site_id' => [
                'required',
                'integer',
                'exists:construction_sites,id',
            ],

            'operator_started_at' => [
                'nullable',
                'date',
            ],

            'operator_finished_at' => [
                'nullable',
                'date',
                'after_or_equal:operator_started_at',
            ],

            'note_operator' => [
                'nullable',
                'string',
            ],
        ];
    }
}
