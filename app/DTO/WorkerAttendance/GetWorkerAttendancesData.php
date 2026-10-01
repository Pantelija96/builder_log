<?php

namespace App\DTO\WorkerAttendance;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\WorkerAttendance\GetWorkerAttendancesRequest;
use Carbon\Carbon;

readonly class GetWorkerAttendancesData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $workerId,
        public ?int $constructionSiteId,
        public ?Carbon $date,
        public ?Carbon $dateCreatedFrom,
        public ?Carbon $dateCreatedTo,
        public ?Carbon $dateFrom,
        public ?Carbon $dateTo,
    ) {}

    public static function fromRequest(
        GetWorkerAttendancesRequest $request,
    ): self {
        return new self(
            list: ListQueryData::fromRequest($request),
            workerId: $request->integer('worker_id') ?: null,
            constructionSiteId: $request->integer('construction_site_id') ?: null,
            date: $request->filled('date') ? $request->date('date') : null,
            dateCreatedFrom: $request->filled('date_created_from') ? $request->date('date_created_from') : null,
            dateCreatedTo: $request->filled('date_created_to') ? $request->date('date_created_to') : null,
            dateFrom: $request->filled('date_from') ? $request->date('date_from') : null,
            dateTo: $request->filled('date_to') ? $request->date('date_to') : null,
        );
    }
}
