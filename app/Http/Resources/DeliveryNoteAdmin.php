<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteAdmin extends JsonResource
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

            'company_id' => $this->company_id,
            'daily_log_id' => $this->daily_log_id,
            'construction_site_id' => $this->construction_site_id,
            'site_manager_id' => $this->site_manager_id,

            'site_manager' => WorkerResource::make(
                $this->whenLoaded('siteManager')
            ),

            'construction_site' => ConstructionSiteResource::make(
                $this->whenLoaded('constructionSite')
            ),

            'daily_log' => DailyLogResource::make(
                $this->whenLoaded('dailyLog')
            ),

            'creator' => WorkerResource::make(
                $this->whenLoaded('creator')
            ),

            'attachments' => AttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),

            'created_at' => $this->created_at,
        ];
    }
}
