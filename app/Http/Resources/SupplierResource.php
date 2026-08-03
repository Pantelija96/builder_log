<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'company_id' => $this->company_id,

            'name' => $this->name,
            'pib' => $this->pib,

            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,

            'contact_first_name' => $this->contact_first_name,
            'contact_last_name' => $this->contact_last_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,

            'is_active' => $this->is_active,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'company' => new CompanyResource(
                $this->whenLoaded('company')
            ),
        ];
    }
}
