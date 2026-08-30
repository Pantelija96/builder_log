<?php

namespace App\Http\Requests\ConstructionSite;

use Illuminate\Foundation\Http\FormRequest;

class GetConstructionSiteStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => [
                'required',
                'date',
            ],

            'date_to' => [
                'required',
                'date',
                'after_or_equal:date_from',
            ],
        ];
    }
}
