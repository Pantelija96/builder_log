<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $worker = $this->user();

        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'pib' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('suppliers', 'pib')
                    ->where(
                        fn ($query) =>
                        $query->where(
                            'company_id',
                            $worker->company_id
                        )
                    ),
            ],

            'address' => [
                'nullable',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'contact_first_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'contact_last_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'contact_email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'contact_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
