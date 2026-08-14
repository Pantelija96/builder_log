<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExcavatorLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'machine_assignment_id' => $this->machine_assignment_id,
            'worker_id' => $this->worker_id,
            'created_by' => $this->created_by,
            'site_manager_started_at' => $this->site_manager_started_at,
            'site_manager_finished_at' => $this->site_manager_finished_at,
            'operator_started_at' => $this->operator_started_at,
            'operator_finished_at' => $this->operator_finished_at,
            'work_hours' => $this->work_hours,
            'start_work_hours' => $this->start_work_hours,
            'finish_work_hours' => $this->finish_work_hours,
            'fuel_added' => $this->fuel_added,
            'fuel_remaining' => $this->fuel_remaining,
            'note_site_manager' => $this->note_site_manager,
            'note_operator' => $this->note_operator,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'machine_assignment' => MachineAssignmentResource::make($this->whenLoaded('machineAssignment')),
            'worker' => WorkerResource::make($this->whenLoaded('worker')),
            'creator' => WorkerResource::make($this->whenLoaded('creator')),
        ];
    }
}
