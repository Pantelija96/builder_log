<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'due_date' => [
                'nullable',
                'date',
            ],

            'site_manager_id' => [
                'nullable',
                Rule::exists('workers', 'id'),
            ],

            'construction_site_id' => [
                'nullable',
                Rule::exists('construction_sites', 'id'),
            ],

//            'attachments' => [
//                'nullable',
//                'array',
//            ],

//            'attachments.*' => [
//                'file',
//                'max:10240',
//            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            if (
                $this->filled('site_manager_id') &&
                $this->filled('construction_site_id')
            ) {

                $validator->errors()->add(
                    'site_manager_id',
                    'Task can be assigned either to a site manager or to a construction site.'
                );

            }

        });
    }
}
