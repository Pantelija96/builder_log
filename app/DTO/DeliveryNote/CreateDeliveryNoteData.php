<?php

namespace App\DTO\DeliveryNote;

use App\Http\Requests\DeliveryNote\CreateDeliveryNoteRequest;
use Illuminate\Http\UploadedFile;

readonly class CreateDeliveryNoteData
{
    /**
     * @param UploadedFile[] $attachments
     */
    public function __construct(
        public int $supplierId,
        public string $name,
        public ?string $description,
        public array $attachments,
    ) {
    }

    public static function fromRequest(CreateDeliveryNoteRequest $request,): self {
        return new self(
            supplierId: $request->integer('supplier_id'),
            name: $request->string('name')->toString(),
            description: $request->string('description')->toString() ?: null,
            attachments: $request->file('attachments', []),
        );
    }
}
