<?php

namespace App\Http\Resources;

use App\Models\DeliveryNote;
use App\Models\Expense;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Attachment
 */
class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->original_name,

            'extension' => $this->extension,

            'mime_type' => $this->mime_type,

            'size' => $this->size,

            'download_url' => route(
                'attachments.download',
                $this->id,
            ),

            'created_at' => $this->created_at,
        ];
    }
}
