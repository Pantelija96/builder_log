<?php

namespace App\Actions\Notification;

use App\Actions\BaseAction;
use App\Models\Notification;

class MarkNotificationAsReadAction extends BaseAction
{
    public function execute(Notification $notification,): Notification {
        $notification->markAsRead();
        return $notification->refresh();
    }
}
