<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [

            'token' => $this->token,

            'worker' => [

                'id' => $this->worker->id,

                'first_name' => $this->worker->first_name,

                'last_name' => $this->worker->last_name,

                'full_name' => $this->worker->full_name,

                'username' => $this->worker->username,

                'role' => $this->worker->role,

                'company' => [

                    'id' => $this->worker->company->id,

                    'name' => $this->worker->company->name,

                ],
            ],

        ];
    }
}
