<?php

namespace App\DTO\Document;

use App\DTO\Requests\ListQueryData;
use App\Enums\DocumentType;
use App\Http\Requests\Document\GetDocumentsRequest;
use Carbon\Carbon;

readonly class GetDocumentsData
{
    public function __construct(
        public int $constructionSiteId,
        public ?int $siteManagerId,
        public ?DocumentType $type,
        public ?int $uploadedBy,
        public ?string $search,
        public ?string $name,
        public ?Carbon $dateFrom,
        public ?Carbon $dateTo,
        public ListQueryData $list,
    ) {}

    public static function fromRequest(GetDocumentsRequest $request,): self
    {
        return new self(
            constructionSiteId: (int) $request->validated('construction_site_id'),
            siteManagerId: $request->validated('site_manager_id'),
            type: $request->enum('type', DocumentType::class,),
            uploadedBy: $request->validated('uploaded_by'),
            search: $request->validated('search'),
            name: $request->validated('name'),
            dateFrom: $request->date('date_from'),
            dateTo: $request->date('date_to'),
            list: ListQueryData::fromRequest($request),
        );
    }
}
