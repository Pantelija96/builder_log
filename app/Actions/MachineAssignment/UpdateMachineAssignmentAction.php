<?php

namespace App\Actions\MachineAssignment;

use App\Actions\BaseAction;
use App\DTO\MachineAssignment\UpdateMachineAssignmentData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class UpdateMachineAssignmentAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        MachineAssignment $assignment,
        UpdateMachineAssignmentData $data,
        Worker $currentWorker,
        ?string $reason = null,
    ): MachineAssignment {

        return $this->transaction(function () use (
            $assignment,
            $data,
            $currentWorker,
            $reason,
        ) {

            if ($this->hasOverlappingAssignment(
                assignment: $assignment,
                startedAt: $data->startedAt,
                finishedAt: $data->finishedAt,
            )) {
                throw new BusinessException(
                    __('Machine is already assigned during this time period.')
                );
            }

            $oldValues = $assignment->getAttributes();

            $assignment->update([
                'started_at' => $data->startedAt,
                'finished_at' => $data->finishedAt,
            ]);

            $this->logging->activity(
                actor: $currentWorker,
                subject: $assignment,
                event: LogEvent::MACHINE_ASSIGNMENT_UPDATED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $assignment,
                event: LogEvent::MACHINE_ASSIGNMENT_UPDATED,
                oldValues: $oldValues,
                newValues: $assignment->fresh()->getAttributes(),
                reason: $reason,
            );

            return $assignment->fresh([
                'machine',
                'constructionSite',
                'siteManager',
                'worker',
                'creator',
            ]);
        });
    }

    private function hasOverlappingAssignment(
        MachineAssignment $assignment,
        Carbon $startedAt,
        ?Carbon $finishedAt,
    ): bool {

        return MachineAssignment::query()
            ->where('machine_id', $assignment->machine_id)
            ->whereKeyNot($assignment->id)
            ->where(
                'started_at',
                '<',
                $finishedAt ?? '9999-12-31 23:59:59',
            )
            ->where(function (Builder $query) use ($startedAt) {
                $query
                    ->whereNull('finished_at')
                    ->orWhere(
                        'finished_at',
                        '>',
                        $startedAt,
                    );
            })
            ->exists();
    }
}
