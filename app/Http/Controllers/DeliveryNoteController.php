<?php

namespace App\Http\Controllers;

use App\DTO\DeliveryNote\CreateDeliveryNoteData;
use App\DTO\DeliveryNote\GetDeliveryNotesData;
use App\Http\Requests\DeliveryNote\CreateDeliveryNoteRequest;
use App\Http\Requests\DeliveryNote\GetDeliveryNotesRequest;
use App\Http\Resources\DeliveryNoteResource;
use App\Models\DailyLog;
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
}
