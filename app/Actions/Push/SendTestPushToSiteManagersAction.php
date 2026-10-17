<?php

namespace App\Actions\Push;

use App\Models\Worker;
use App\Services\WorkerPushNotificationService;

class SendTestPushToSiteManagersAction
{
    public function __construct(
        private readonly WorkerPushNotificationService $workerPushNotificationService,
    ) {}

    public function execute(Worker $admin): array
    {
        $siteManagers = Worker::query()
            ->where('company_id', $admin->company_id)
            ->siteManagers()
            ->where('is_active', true)
            ->with('activeDevices')
            ->get();

        $devicesCount = 0;

        foreach ($siteManagers as $siteManager) {
            $devicesCount += $siteManager->activeDevices->count();

            $this->workerPushNotificationService->send(
                worker: $siteManager,
                title: 'BuilderLog test',
                body: 'Test push notification from BuilderLog.',
                data: [
                    'type' => 'test',
                ],
            );
        }

        return [
            'site_managers' => $siteManagers->count(),
            'devices' => $devicesCount,
        ];
    }
}
