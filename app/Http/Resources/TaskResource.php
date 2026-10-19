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
        $taskNotification = $this->relationLoaded('notifications')
            ? $this->notifications->first()
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date?->toDateString(),

            'read_at' => $this->read_at,
            'is_read' => $this->isRead(),

            'notification_read_at' => $taskNotification?->read_at,

            'notification_is_read' => !$taskNotification || $taskNotification->read_at !== null,

            'completed_at' => $this->completed_at,
            'is_completed' => $this->isCompleted(),

            'company_id' => $this->company_id,
            'worker_id' => $this->worker_id,
            'target_role' => $this->target_role,
            'construction_site_id' => $this->construction_site_id,

            'creator' => WorkerResource::make(
                $this->whenLoaded('creator')
            ),

            'worker' => WorkerResource::make(
                $this->whenLoaded('worker')
            ),

            'construction_site' => ConstructionSiteResource::make(
                $this->whenLoaded('constructionSite')
            ),

            'completed_by' => WorkerResource::make(
                $this->whenLoaded('completedBy')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
