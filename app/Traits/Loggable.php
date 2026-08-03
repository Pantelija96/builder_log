<?php

namespace App\Traits;

trait Loggable
{
    public function logContext(): array
    {
        return [
            'company_id' => $this->company_id,
            'daily_log_id' => $this->daily_log_id,
            'construction_site_id' => $this->construction_site_id,
            'date' => $this->date,
        ];
    }
}
