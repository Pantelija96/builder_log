<?php

namespace App\Services\Storage;

use App\DTO\Document\StoredDocumentData;
use App\Models\Document;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStorageService
{
    private readonly Filesystem $storage;

    private readonly string $directory;

    public function __construct()
    {
        $this->storage = Storage::disk(
            config('filesystems.documents.disk')
        );

        $this->directory = config(
            'filesystems.documents.directory'
        );
    }

    public function store(
        UploadedFile $file,
    ): StoredDocumentData {

        $name = $file->hashName();

        $path = $this->storage->putFileAs(
            $this->directory,
            $file,
            $name,
        );

        return new StoredDocumentData(
            name: $name,
            path: $path,
            mimeType: $file->getMimeType(),
            size: $file->getSize(),
        );
    }

    public function delete(
        Document $document,
    ): void {

        $this->storage->delete(
            $document->path,
        );
    }

    public function download(
        Document $document,
    ): StreamedResponse {

        return $this->storage->download(
            $document->path,
            $document->original_name,
        );
    }
}
