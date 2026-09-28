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
            'due_date' => $this->due_date?->toDateString(),
            'read_at' => $this->read_at,
            'completed_at' => $this->completed_at,
            'is_read' => $this->isRead(),
            'is_completed' => $this->isCompleted(),
            'company_id' => $this->company_id,
            'worker_id' => $this->worker_id,
            'target_role' => $this->target_role,
            'construction_site_id' => $this->construction_site_id,
            'creator' => WorkerResource::make($this->whenLoaded('creator')),
            'worker' => WorkerResource::make($this->whenLoaded('worker')),
            'construction_site' => ConstructionSiteResource::make($this->whenLoaded('constructionSite')),
            'completed_by' => WorkerResource::make($this->whenLoaded('completedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
