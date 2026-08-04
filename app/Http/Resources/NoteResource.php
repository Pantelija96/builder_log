<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Note
 */
class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'note' => $this->note,

            'notify_admin' => $this->notify_admin,

            'date' => $this->date,

            'company_id' => $this->company_id,

            'daily_log_id' => $this->daily_log_id,

            'construction_site_id' => $this->construction_site_id,

            'site_manager_id' => $this->site_manager_id,

            'creator' => WorkerResource::make(
                $this->whenLoaded('creator')
            ),

            'site_manager' => WorkerResource::make(
                $this->whenLoaded('siteManager')
            ),

            'construction_site' => ConstructionSiteResource::make(
                $this->whenLoaded('constructionSite')
            ),

            'daily_log' => DailyLogResource::make(
                $this->whenLoaded('dailyLog')
            ),

            'attachments' => AttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
