<?php

namespace App\Http\Requests\Push;

use Illuminate\Foundation\Http\FormRequest;

class RegisterWorkerDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => [
                'required',
                'string',
                'in:android,ios',
            ],

            'device_uuid' => [
                'nullable',
                'string',
                'max:100',
            ],

            'fcm_token' => [
                'required',
                'string',
                'max:512',
            ],

            'device_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'app_version' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }
}
