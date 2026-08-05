<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Task
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'title' => $this->title,
            'description' => $this->description,

            'due_date' => $this->due_date,

            'read_at' => $this->read_at,
            'completed_at' => $this->completed_at,

            'is_read' => $this->isRead(),
            'is_completed' => $this->isCompleted(),

            'company_id' => $this->company_id,
            'site_manager_id' => $this->site_manager_id,
            'construction_site_id' => $this->construction_site_id,

            'creator' => WorkerResource::make(
                $this->whenLoaded('creator')
            ),

            'site_manager' => WorkerResource::make(
                $this->whenLoaded('siteManager')
            ),

            'construction_site' => ConstructionSiteResource::make(
                $this->whenLoaded('constructionSite')
            ),

            'completed_by' => WorkerResource::make(
                $this->whenLoaded('completedBy')
            ),

//            'attachments' => AttachmentResource::collection(
//                $this->whenLoaded('attachments')
//            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
