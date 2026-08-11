<?php

namespace App\Traits;

use App\Models\MachineAssignment;

trait Loggable
{
    public function logContext(): array
    {
        if ($this instanceof MachineAssignment) {
            return [
                'company_id' => $this->company_id,
                'daily_log_id' => $this->daily_log_id,
                'construction_site_id' => $this->construction_site_id,
                'date' => $this->date,
            ];
        }

        if (
            method_exists($this, 'machineAssignment')
            && $this->machineAssignment
        ) {
            /** @var MachineAssignment $assignment */
            $assignment = $this->machineAssignment;

            return [
                'company_id' => $assignment->company_id,
                'daily_log_id' => $assignment->daily_log_id,
                'construction_site_id' =>
                    $assignment->construction_site_id,
                'date' => $assignment->date,
            ];
        }

        return [
            'company_id' => $this->company_id,
            'daily_log_id' => $this->daily_log_id,
            'construction_site_id' => $this->construction_site_id,
            'date' => $this->date,
        ];
    }
}
