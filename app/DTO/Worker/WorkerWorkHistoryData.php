<?php

namespace App\DTO\Worker;

use App\Models\Worker;
use Illuminate\Database\Eloquent\Collection;

readonly class WorkerWorkHistoryData
{
    public function __construct(
        public Worker $worker,
        public string $type,
        public Collection $history,
    ) {}
}
