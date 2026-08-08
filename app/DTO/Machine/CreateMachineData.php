<?php

namespace App\DTO\Machine;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Enums\OwnerType;
use App\Http\Requests\Machine\CreateMachineRequest;

readonly class CreateMachineData
{
    public function __construct(
        public string $name,
        public MachineType $type,
        public OwnerType $ownerType,
        public int $ownerId,
        public MachineStatus $status,
        public ?string $imagePath,
    ) {
    }

    public static function fromRequest(
        CreateMachineRequest $request,
    ): self {
        return new self(
            name: $request->validated('name'),
            type: $request->enum(
                'type',
                MachineType::class,
            ),
            ownerType: $request->enum(
                'owner_type',
                OwnerType::class,
            ),
            ownerId: (int) $request->validated('owner_id'),
            status: $request->enum(
            'status',
            MachineStatus::class,
        ) ?? MachineStatus::ACTIVE,
            imagePath: $request->validated('image_path'),
        );
    }
}
