<?php

namespace App\Http\Resources;

use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Worker */
class DailyLogSiteManagerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var string|null $joinedAt */
        $joinedAt = $this->pivot->created_at?->toISOString();

        return [
            ...WorkerResource::make($this->resource)->toArray($request),
            'joined_at' => $joinedAt,
        ];
    }
}
