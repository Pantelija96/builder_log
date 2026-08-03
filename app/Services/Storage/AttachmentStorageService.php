<?php

namespace App\Services\Storage;

use App\DTO\Attachment\StoredAttachmentData;
use App\Models\Attachment;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentStorageService
{
    private readonly Filesystem $storage;

    private readonly string $directory;

    public function __construct()
    {
        $this->storage = Storage::disk(
            config('filesystems.attachments.disk')
        );

        $this->directory = config(
            'filesystems.attachments.directory'
        );
    }

    public function store(UploadedFile $file,): StoredAttachmentData {
        $name = $file->hashName();

        $path = $this->storage->putFileAs(
            $this->directory,
            $file,
            $name,
        );

        return new StoredAttachmentData(
            name: $name,
            path: $path,
            mimeType: $file->getMimeType(),
            size: $file->getSize(),
        );
    }

    public function delete(Attachment $attachment,): void {
        $this->storage->delete(
            $attachment->path,
        );
    }

    public function download(Attachment $attachment,): StreamedResponse {
        return Storage::disk(
            config('filesystems.attachments.disk')
        )->download(
            $attachment->path,
            $attachment->original_name,
        );
    }
}
