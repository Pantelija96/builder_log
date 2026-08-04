<?php

namespace App\DTO\DeliveryNote;

use App\Http\Requests\DeliveryNote\UpdateDeliveryNoteRequest;

readonly class UpdateDeliveryNoteData
{
    public function __construct(
        public int $supplierId,
        public string $name,
        public ?string $description,
        public array $attachments,
    ) {
    }

    public static function fromRequest(UpdateDeliveryNoteRequest $request): self
    {
        return new self(
            supplierId: $request->integer('supplier_id'),

            name: $request->string('name')->toString(),

            description: $request->filled('description')
                ? $request->string('description')->toString()
                : null,

            attachments: $request->file('attachments', []),
        );
    }
}
