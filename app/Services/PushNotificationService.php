<?php

namespace App\Services;

use App\Actions\Push\SendTestPushToSiteManagersAction;
use App\Models\Worker;

class PushNotificationService
{
    public function __construct(
        private readonly SendTestPushToSiteManagersAction $sendTestPushToSiteManagersAction,
    ) {}

    public function sendTestToSiteManagers(Worker $admin): array
    {
        return $this->sendTestPushToSiteManagersAction->execute($admin);
    }
}
