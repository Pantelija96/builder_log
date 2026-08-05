<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\Worker;

class MarkAllNotificationsAsReadAction
{
    public function execute(Worker $worker): int
    {
        return Notification::query()
            ->whereBelongsTo($worker)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }
}
