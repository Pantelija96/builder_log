<?php

namespace App\Services;

use App\Actions\TruckLog\CreateTruckLogAction;
use App\Actions\TruckLog\DeleteTruckLogAction;
use App\Actions\TruckLog\GetAvailableTrucksAction;
use App\Actions\TruckLog\GetOccupiedTrucksAction;
use App\Actions\TruckLog\UpdateTruckLogAction;
use App\DTO\TruckLog\CreateTruckLogData;
use App\DTO\TruckLog\CreateTruckLogForDriverData;
use App\DTO\TruckLog\UpdateTruckLogData;
use App\Models\DailyLog;
use App\Models\TruckLog;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Collection;

class TruckLogService
{
    public function __construct(
        private readonly CreateTruckLogAction $createTruckLogAction,
        private readonly UpdateTruckLogAction $updateTruckLogAction,
        private readonly DeleteTruckLogAction $deleteTruckLogAction,
        private readonly GetAvailableTrucksAction $getAvailableTrucksAction,
        private readonly GetOccupiedTrucksAction $getOccupiedTrucksAction,
    ) {}

    public function create(
        DailyLog $dailyLog,
        CreateTruckLogData $data,
        Worker $currentWorker,
    ): TruckLog {
        return $this->createTruckLogAction->execute(
            dailyLog: $dailyLog,
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function createForDriver(
        CreateTruckLogForDriverData $data,
        Worker $currentWorker,
    ): TruckLog {
        return $this->createTruckLogAction->executeForDriver(
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function getAvailable(
        Worker $currentWorker,
    ): Collection {
        return $this->getAvailableTrucksAction->execute(
            currentWorker: $currentWorker,
        );
    }

    public function getOccupied(
        Worker $currentWorker,
    ): Collection {
        return $this->getOccupiedTrucksAction->execute(
            currentWorker: $currentWorker,
        );
    }

    public function update(
        TruckLog $truckLog,
        UpdateTruckLogData $data,
        Worker $currentWorker,
        ?string $reason = null,
    ): TruckLog {

        return $this->updateTruckLogAction->execute(
            truckLog: $truckLog,
            data: $data,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    public function delete(
        TruckLog $truckLog,
        Worker $currentWorker,
        string $reason,
    ): void {

        $this->deleteTruckLogAction->execute(
            truckLog: $truckLog,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }
}
