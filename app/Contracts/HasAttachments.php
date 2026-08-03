<?php

namespace App\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasAttachments
{
    public function attachments(): MorphMany;

    public function attachmentDailyLogId(): int;

    public function attachmentCompanyId(): int;

    public function attachmentDate(): CarbonInterface;
}
