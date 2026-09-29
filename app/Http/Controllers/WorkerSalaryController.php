<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkerSalaryResource;
use App\Models\Worker;
use App\Services\WorkerSalaryService;
use Illuminate\Http\Request;

class WorkerSalaryController extends ApiController
{
    public function __construct(
        private readonly WorkerSalaryService $service,
    ) {
    }

    public function show(Worker $worker, Request $request,) {
        /** @var Worker $currentWorker */
        $currentWorker = $request->user();

        $balance = $this->service->getBalance(
            worker: $worker,
            currentWorker: $currentWorker,
        );

        return WorkerSalaryResource::make($balance);
    }
}
