<?php

namespace App\Actions\Document;

use App\Models\Document;
use App\Services\Storage\DocumentStorageService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadDocumentAction
{
    public function __construct(
        private readonly DocumentStorageService $storage,
    ) {
    }

    public function execute(
        Document $document,
    ): StreamedResponse {

        return $this->storage->download(
            $document,
        );
    }
}
