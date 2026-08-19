<?php

namespace App\Http\Requests\SiteManager;

use Illuminate\Foundation\Http\FormRequest;

class GetSiteManagerOverallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_created_from' => [
                'nullable',
                'date',
            ],

            'date_created_to' => [
                'nullable',
                'date',
            ],
        ];
    }
}
