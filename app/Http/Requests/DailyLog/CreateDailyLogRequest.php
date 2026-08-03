<?php

namespace App\Http\Requests\DailyLog;

use Illuminate\Foundation\Http\FormRequest;

class CreateDailyLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'exists:companies,id',
            ],

            'construction_site_id' => [
                'required',
                'exists:construction_sites,id',
            ],

            'site_manager_id' => [
                'required',
                'exists:workers,id',
            ],

            'date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
        ];
    }
}
