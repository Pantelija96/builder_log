<?php

namespace App\DTO\Document;

use App\Enums\DocumentType;
use App\Http\Requests\Document\UpdateDocumentRequest;

readonly class UpdateDocumentData
{
    public function __construct(
        public int $constructionSiteId,
        public ?int $siteManagerId,
        public string $name,
        public ?DocumentType $type,
    ) {}

    public static function fromRequest(UpdateDocumentRequest $request,): self
    {
        return new self(
            constructionSiteId: (int) $request->validated('construction_site_id'),
            siteManagerId: $request->validated('site_manager_id'),
            name: $request->validated('name'),
            type: $request->enum('type', DocumentType::class,),
        );
    }
}

