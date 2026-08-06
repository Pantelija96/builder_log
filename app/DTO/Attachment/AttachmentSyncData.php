<?php

namespace App\DTO\Attachment;

readonly class AttachmentSyncData
{
    public function __construct(
        public array $attachments = [],
        public array $deleteAttachments = [],
    ) {
    }
}
