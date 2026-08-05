<?php

namespace App\Http\Controllers;

use App\DTO\Notification\GetNotificationsData;
use App\Http\Requests\Notification\GetNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\Worker;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends ApiController
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function index(
        GetNotificationsRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(

            NotificationResource::collection(

                $this->notificationService->get(
                    worker: $worker,
                    data: GetNotificationsData::fromRequest($request),
                )

            )

        );
    }

    public function unreadCount(): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success([
            'count' => $this->notificationService
                ->unreadCount($worker),
        ]);
    }

    public function read(
        Notification $notification,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(

            NotificationResource::make(

                $this->notificationService->markAsRead(
                    worker: $worker,
                    notification: $notification,
                )

            ),

            'Notification marked as read.'

        );
    }
}
