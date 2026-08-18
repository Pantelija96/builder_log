<?php

namespace App\Http\Controllers;

use App\DTO\DeliveryNote\CreateDeliveryNoteData;
use App\DTO\DeliveryNote\GetDeliveryNotesData;
use App\DTO\DeliveryNote\UpdateDeliveryNoteData;
use App\Http\Requests\DeliveryNote\CreateDeliveryNoteRequest;
use App\Http\Requests\DeliveryNote\DeleteDeliveryNoteRequest;
use App\Http\Requests\DeliveryNote\GetDeliveryNotesRequest;
use App\Http\Requests\DeliveryNote\UpdateDeliveryNoteRequest;
use App\Http\Resources\DeliveryNoteAdmin;
use App\Http\Resources\DeliveryNoteResource;
use App\Models\DailyLog;
use App\Models\DeliveryNote;
use App\Models\Worker;
use App\Services\DeliveryNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryNoteController extends ApiController
{
    public function __construct(
        private readonly DeliveryNoteService $service,
    ) {
    }

    public function getAll(GetDeliveryNotesRequest $request,): JsonResponse
    {
        return $this->success(
            DeliveryNoteAdmin::collection(
                $this->service->getAllAdmin(
                    GetDeliveryNotesData::fromRequest($request),
                )
            )
        );
    }

    public function index(DailyLog $dailyLog, GetDeliveryNotesRequest $request,): JsonResponse {
        $data = GetDeliveryNotesData::fromRequest($request);

        $deliveryNotes = $this->service->getAll(
            $dailyLog,
            $data,
        );

        return $this->success(
            DeliveryNoteResource::collection($deliveryNotes)
        );
    }

    public function store(DailyLog $dailyLog, CreateDeliveryNoteRequest $request,): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $deliveryNote = $this->service->create(
            $dailyLog,
            CreateDeliveryNoteData::fromRequest($request),
            $worker
        );

        return $this->success(
            new DeliveryNoteResource($deliveryNote)
        );
    }

    public function update(
        DailyLog $dailyLog,
        DeliveryNote $deliveryNote,
        UpdateDeliveryNoteRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            DeliveryNoteResource::make(
                $this->service->update(
                    dailyLog: $dailyLog,
                    deliveryNote: $deliveryNote,
                    data: UpdateDeliveryNoteData::fromRequest($request),
                    worker: $worker,
                    reason: $request->string('reason')->toString(),
                )
            ),
            'Delivery note updated successfully.'
        );
    }

    public function destroy(
        DailyLog $dailyLog,
        DeliveryNote $deliveryNote,
        DeleteDeliveryNoteRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $this->service->delete(
            dailyLog: $dailyLog,
            deliveryNote: $deliveryNote,
            worker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Delivery note deleted successfully.'
        );
    }
}
