<?php

namespace App\DTO\Document;

use App\Enums\DocumentType;
use App\Http\Requests\Document\CreateDocumentRequest;

readonly class CreateDocumentData
{
    public function __construct(
        public int $constructionSiteId,
        public ?int $siteManagerId,
        public ?DocumentType $type,
        public array $files,
    ) {
    }

    public static function fromRequest(CreateDocumentRequest $request,): self
    {
        return new self(
            constructionSiteId: (int) $request->validated('construction_site_id'),
            siteManagerId: $request->validated('site_manager_id'),
            type: $request->enum('type', DocumentType::class,),
            files: $request->file('files', [],),
        );
    }
}
