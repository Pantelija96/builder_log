<?php

namespace App\DTO\Document;

readonly class StoredDocumentData
{
    public function __construct(
        public string $name,
        public string $path,
        public string $mimeType,
        public int $size,
    ) {
    }
}
