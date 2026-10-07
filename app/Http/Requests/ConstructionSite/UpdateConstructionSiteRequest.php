<?php

namespace App\Http\Requests\ConstructionSite;

use App\Enums\ConstructionSiteStatus;
use App\Enums\WorkerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConstructionSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'status' => [
                'required',
                Rule::enum(ConstructionSiteStatus::class),
            ],

            'site_manager_ids' => [
                'required',
                'array',
            ],

            'site_manager_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('workers', 'id')->where('role', WorkerRole::SITE_MANAGER->value),
            ],
        ];
    }
}
