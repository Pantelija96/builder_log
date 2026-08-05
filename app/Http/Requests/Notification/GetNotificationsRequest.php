<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetNotificationsRequest extends FormRequest
{
    public function rules(): array
    {
        return [

            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'type' => [
                'nullable',
                Rule::in([
                    'task',
                ]),
            ],

            'is_read' => [
                'nullable',
                'boolean',
            ],

            'created_from' => [
                'nullable',
                'date',
            ],

            'created_to' => [
                'nullable',
                'date',
                'after_or_equal:created_from',
            ],

            'sort' => [
                'nullable',
                'string',
            ],

            'direction' => [
                'nullable',
                'in:asc,desc',
            ],

            'offset' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
