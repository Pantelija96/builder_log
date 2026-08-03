<?php

namespace App\DTO\Attachment;

readonly class StoredAttachmentData
{
    public function __construct(
        public string $name,
        public string $path,
        public string $mimeType,
        public int $size,
    ) {
    }
}
