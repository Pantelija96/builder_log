<?php

namespace App\Http\Requests\ConstructionSite;

use Illuminate\Foundation\Http\FormRequest;

class DeleteConstructionSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }
}
