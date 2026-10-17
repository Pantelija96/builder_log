<?php

namespace App\Http\Resources;

use App\Enums\NotificationType;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Task
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $worker = $request->user();

        $taskNotification = null;

        if ($worker !== null) {
            $taskNotification = Notification::query()
                ->where('worker_id', $worker->id)
                ->where('type', NotificationType::TASK)
                ->where('payload->task_id', $this->id)
                ->orderBy('id')
                ->first();
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date?->toDateString(),

            /*
             * Existing global Task read state.
             *
             * Keep these fields unchanged for backwards compatibility.
             */
            'read_at' => $this->read_at,
            'is_read' => $this->isRead(),

            /*
             * Per-worker notification read state.
             *
             * This tells the frontend whether the notification belonging
             * to THIS task and the currently authenticated worker has
             * already been read.
             */
            'notification_read_at' => $taskNotification?->read_at,
            'notification_is_read' => $taskNotification?->read_at !== null,

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
