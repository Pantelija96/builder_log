<?php

namespace App\DTO\Machine;

use App\Enums\MachineStatus;
use App\Enums\OwnerType;
use App\Http\Requests\Machine\UpdateMachineRequest;

readonly class UpdateMachineData
{
    public function __construct(
        public string $name,
        public OwnerType $ownerType,
        public int $ownerId,
        public MachineStatus $status,
        public ?string $imagePath,
    ) {
    }

    public static function fromRequest(
        UpdateMachineRequest $request,
    ): self {
        return new self(
            name: $request->validated('name'),
            ownerType: $request->enum(
                'owner_type',
                OwnerType::class,
            ),
            ownerId: (int) $request->validated('owner_id'),
            status: $request->enum(
                'status',
                MachineStatus::class,
            ),
            imagePath: $request->validated('image_path'),
        );
    }
}
