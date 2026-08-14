<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\Subcontractor;
use App\Models\Supplier;
use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MachineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'image_path' => $this->image_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'company' => new CompanyResource(
                $this->whenLoaded('company')
            ),
            'owner' => match (true) {
                $this->owner instanceof Company => new CompanyResource($this->owner),
                $this->owner instanceof Supplier => new SupplierResource($this->owner),
                $this->owner instanceof Subcontractor => new SubcontractorResource($this->owner),
                $this->owner instanceof Worker => new WorkerResource($this->owner),
                default => null,
            },
            'excavator' => $this->when(
                $this->isExcavator() && $this->relationLoaded('excavator'),
                fn () => ExcavatorResource::make($this->excavator),
            ),
        ];
    }
}
