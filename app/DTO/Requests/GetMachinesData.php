<?php

namespace App\DTO\Requests;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Enums\OwnerType;
use App\Http\Requests\Get\GetMachinesRequest;

readonly class GetMachinesData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $companyId,
        public ?MachineType $type,
        public ?string $name,
        public ?MachineStatus $status,
        public ?OwnerType $ownerType,
        public ?int $ownerId,
        public ?MachineType $exclude_type,
    ) {
    }

    public static function fromRequest(GetMachinesRequest $request): self
    {
        return new self(
            list: ListQueryData::fromRequest($request),

            companyId: $request->validated('company_id'),

            type: $request->enum('type', MachineType::class),

            name: $request->validated('name'),

            status: $request->enum('status', MachineStatus::class),

            ownerType: $request->enum('owner_type', OwnerType::class),

            ownerId: $request->validated('owner_id'),

            exclude_type: $request->enum('exclude_type', MachineType::class),
        );
    }
}
