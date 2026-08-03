<?php

namespace App\Traits;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait InteractsWithAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(
            Attachment::class,
            'attachable'
        );
    }

    public function attachmentCount(): int
    {
        return $this->attachments()->count();
    }

    public function hasAttachments(): bool
    {
        return $this->attachments()->exists();
    }

    public function canAddAttachment(): bool
    {
        return $this->attachments()->count() < 15;
    }
}
