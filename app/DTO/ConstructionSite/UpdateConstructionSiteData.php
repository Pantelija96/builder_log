<?php

namespace App\DTO\ConstructionSite;

use App\Enums\ConstructionSiteStatus;
use App\Http\Requests\ConstructionSite\UpdateConstructionSiteRequest;

readonly class UpdateConstructionSiteData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $address,
        public ?float $latitude,
        public ?float $longitude,
        public ConstructionSiteStatus $status,
        public array $siteManagerIds,
    ) {
    }

    public static function fromRequest(
        UpdateConstructionSiteRequest $request,
    ): self {
        return new self(
            name: $request->validated('name'),
            description: $request->validated('description'),
            address: $request->validated('address'),
            latitude: $request->filled('latitude') ? (float) $request->validated('latitude') : null,
            longitude: $request->filled('longitude') ? (float) $request->validated('longitude') : null,
            status: $request->enum('status', ConstructionSiteStatus::class,),
            siteManagerIds: $request->validated('site_manager_ids'),
        );
    }
}
