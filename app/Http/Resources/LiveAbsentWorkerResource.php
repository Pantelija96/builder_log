<?php

namespace App\Http\Resources;

use App\Models\Worker;
use Illuminate\Http\Request;

/** @mixin Worker */
class LiveAbsentWorkerResource extends WorkerResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'periods' => WorkerAttendancePeriodResource::collection($this->attendances),
        ];
    }
}
