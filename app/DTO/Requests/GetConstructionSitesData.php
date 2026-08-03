<?php

namespace App\DTO\Requests;

use App\Enums\ConstructionSiteStatus;
use App\Http\Requests\Get\GetConstructionSitesRequest;

readonly class GetConstructionSitesData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $companyId,
        public ?string $name,
        public ?string $address,
        public ?ConstructionSiteStatus $status,
    ) {
    }

    public static function fromRequest(GetConstructionSitesRequest $request): self
    {
        return new self(
            list: ListQueryData::fromRequest($request),

            companyId: $request->validated('company_id'),

            name: $request->validated('name'),
            address: $request->validated('address'),

            status: $request->enum(
                'status',
                ConstructionSiteStatus::class
            ),
        );
    }
}
