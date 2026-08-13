<?php

namespace App\Http\Requests\Excavator;

use Illuminate\Foundation\Http\FormRequest;

class CreateExcavatorRequest extends FormRequest
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

            'initial_work_hours' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }
}
