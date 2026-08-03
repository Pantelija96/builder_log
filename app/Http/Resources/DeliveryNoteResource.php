<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier' => SupplierResource::make(
                $this->whenLoaded('supplier')
            ),
            'name' => $this->name,
            'description' => $this->description,
            'date' => $this->date,
            'attachments' => AttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
            'created_at' => $this->created_at,
        ];
    }
}
