<?php

namespace App\Actions\Document;

use App\DTO\Document\StoredDocumentData;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\UploadedFile;

class UploadDocumentAction
{
    public function __construct(
        private readonly DocumentStorageService $storage,
    ) {
    }

    public function execute(
        UploadedFile $file,
    ): StoredDocumentData {

        return $this->storage->store(
            $file,
        );
    }
}
