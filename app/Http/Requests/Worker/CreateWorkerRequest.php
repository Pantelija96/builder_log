<?php

namespace App\Http\Requests\Worker;

use App\Enums\WorkerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'role' => [
                'required',
                Rule::enum(WorkerRole::class),
            ],

            'username' => [
                'required',
                'string',
                'max:100',
                'unique:workers,username',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
