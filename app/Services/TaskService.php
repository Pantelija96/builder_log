<?php

namespace App\Services;

use App\Actions\Task\CompleteTaskAction;
use App\Actions\Task\CreateTaskAction;
use App\Actions\Task\DeleteTaskAction;
use App\Actions\Task\MarkTaskAsReadAction;
use App\Actions\Task\ReopenTaskAction;
use App\Actions\Task\UpdateTaskAction;
use App\DTO\Task\CreateTaskData;
use App\DTO\Task\GetTasksData;
use App\DTO\Task\UpdateTaskData;
use App\Enums\NotificationType;
use App\Models\Task;
use App\Models\Worker;
use App\QueryFilters\TaskFilter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class TaskService
{
    public function __construct(
        private readonly CreateTaskAction $create,
        private readonly UpdateTaskAction $update,
        private readonly DeleteTaskAction $delete,
        private readonly MarkTaskAsReadAction $markAsRead,
        private readonly CompleteTaskAction $complete,
        private readonly ReopenTaskAction $reopen,
    ) {}

    /**
     * Generic task query.
     *
     * Used for admin/backoffice endpoints where we are not
     * filtering tasks for one specific worker.
     */
    private function query(): Builder
    {
        return Task::query()
            ->with([
                'creator',
                'worker',
                'constructionSite',
                'completedBy',
            ]);
    }

    /**
     * Tasks visible to one specific worker.
     *
     * A worker can see:
     *
     * 1. A task directly assigned to them.
     * 2. A general role task assigned to their role.
     * 3. If they are a Site Manager, a construction-site task
     *    for a site they are assigned to.
     *
     * Task notifications are also loaded only for this worker.
     */
    private function myTasksQuery(Worker $worker): Builder
    {
        return Task::query()
            ->with([
                'creator',
                'worker',
                'constructionSite',
                'completedBy',

                'notifications' => function ($query) use ($worker) {
                    $query
                        ->where('worker_id', $worker->id)
                        ->where('type', NotificationType::TASK)
                        ->latest('id');
                },
            ])
            ->where('company_id', $worker->company_id)
            ->where(function (Builder $query) use ($worker) {

                /*
                 * 1. Directly assigned task.
                 *
                 * Example:
                 * worker_id = 15
                 */
                $query->where(function (Builder $query) use ($worker) {
                    $query->where('worker_id', $worker->id);
                });

                /*
                 * 2. Role task.
                 *
                 * Example:
                 *
                 * worker_id = null
                 * construction_site_id = null
                 * target_role = site_manager
                 *
                 * All workers with that role can see it.
                 */
                $query->orWhere(function (Builder $query) use ($worker) {
                    $query
                        ->whereNull('worker_id')
                        ->whereNull('construction_site_id')
                        ->where('target_role', $worker->role);
                });

                /*
                 * 3. Construction-site task.
                 *
                 * Only Site Managers assigned to that site can see it.
                 *
                 * The task must not be directly assigned to another worker.
                 */
                if ($worker->isSiteManager()) {
                    $query->orWhere(function (Builder $query) use ($worker) {
                        $query
                            ->whereNull('worker_id')
                            ->whereNotNull('construction_site_id')
                            ->whereHas(
                                'constructionSite.siteManagers',
                                fn (Builder $query) =>
                                $query->whereKey($worker->id)
                            );
                    });
                }
            });
    }

    public function create(
        CreateTaskData $data,
        Worker $worker,
    ): Task {
        return $this->create->execute(
            $data,
            $worker,
        );
    }

    /**
     * Generic/admin list of tasks.
     */
    public function get(
        GetTasksData $data,
    ): Collection {
        return (new TaskFilter($data))
            ->apply(
                $this->query()
            )
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    /**
     * Tasks visible to the authenticated worker.
     */
    public function getMyTasks(
        Worker $worker,
        GetTasksData $data,
    ): Collection {
        return (new TaskFilter($data))
            ->apply(
                $this->myTasksQuery($worker)
            )
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    /**
     * Generic/admin lookup.
     */
    public function findById(
        int $id,
    ): ?Task {
        return $this->query()
            ->find($id);
    }

    /**
     * Worker-specific lookup.
     *
     * Important for Android/mobile endpoints:
     * prevents a worker from fetching a task that is not
     * actually assigned/visible to them.
     *
     * Also loads only this worker's notification relation,
     * so TaskResource gets the correct read state.
     */
    public function findMyTaskById(
        int $id,
        Worker $worker,
    ): ?Task {
        return $this->myTasksQuery($worker)
            ->find($id);
    }

    public function update(
        Task $task,
        UpdateTaskData $data,
        Worker $worker,
        ?string $reason,
    ): Task {
        return $this->update->execute(
            $task,
            $data,
            $worker,
            $reason,
        );
    }

    public function delete(
        Task $task,
        Worker $worker,
        string $reason,
    ): void {
        $this->delete->execute(
            $task,
            $worker,
            $reason,
        );
    }

    public function markAsRead(
        Task $task,
        Worker $worker,
    ): Task {
        return $this->markAsRead->execute(
            task: $task,
            worker: $worker,
        );
    }

    public function complete(
        Task $task,
        Worker $worker,
    ): Task {
        return $this->complete->execute(
            $task,
            $worker,
        );
    }

    public function reopen(
        Task $task,
        Worker $worker,
    ): Task {
        return $this->reopen->execute(
            $task,
            $worker,
        );
    }
}
