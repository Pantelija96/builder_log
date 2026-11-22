<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'construction_site_id' => $this->construction_site_id,
            'date' => $this->date,
            'is_locked' => $this->is_locked,
            'locked_at' => $this->locked_at,
            'locked_by_id' => $this->locked_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'company' => CompanyResource::make(
                $this->whenLoaded('company')
            ),
            'construction_site' => ConstructionSiteResource::make(
                $this->whenLoaded('constructionSite')
            ),
            'site_managers' => DailyLogSiteManagerResource::collection($this->whenLoaded('siteManagers')),
            'locked_by' => WorkerResource::make(
                $this->whenLoaded('lockedBy')
            ),
            'attachments' => AttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
        ];
    }
}
