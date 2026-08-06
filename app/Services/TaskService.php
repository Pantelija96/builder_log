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
use App\Models\Task;
use App\Models\Worker;
use App\QueryFilters\TaskFilter;
use Illuminate\Database\Eloquent\Collection;

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
    private function query()
    {
        return Task::query()
            ->with([
                'creator',
                'siteManager',
                'constructionSite',
                'completedBy',
//                'attachments',
            ]);
    }
    private function myTasksQuery(Worker $worker,)
    {
        return Task::query()

            ->with([
                'creator',
                'siteManager',
                'constructionSite',
                'completedBy',
//                'attachments',
            ])

            ->where(function ($query) use ($worker) {

                $query

                    ->where('site_manager_id', $worker->id)

                    ->orWhereHas(
                        'constructionSite.siteManagers',
                        fn ($q) => $q->whereKey($worker->id)
                    )

                    ->orWhere(function ($query) {

                        $query
                            ->whereNull('site_manager_id')
                            ->whereNull('construction_site_id');

                    });

            });
    }

    public function create(CreateTaskData $data, Worker $worker,): Task {
        return $this->create->execute(
            $data,
            $worker,
        );
    }
    public function get(GetTasksData $data,): Collection {
        return (new TaskFilter($data))
            ->apply(
                $this->query()
            )
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }
    public function getMyTasks(Worker $worker, GetTasksData $data,): Collection {
        return (new TaskFilter($data))
            ->apply(
                $this->myTasksQuery($worker)
            )
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }
    public function findById(int $id,): ?Task {
        return $this->query()->find($id);
    }
    public function update(Task $task, UpdateTaskData $data, Worker $worker, ?string $reason,): Task {
        return $this->update->execute(
            $task,
            $data,
            $worker,
            $reason,
        );
    }
    public function delete(Task $task, Worker $worker, string $reason,): void {
        $this->delete->execute(
            $task,
            $worker,
            $reason,
        );
    }
    public function markAsRead(Task $task, Worker $worker,): Task
    {
        return $this->markAsRead->execute(
            task: $task,
            worker: $worker,
        );
    }
    public function complete(Task $task, Worker $worker,): Task {
        return $this->complete->execute(
            $task,
            $worker,
        );
    }
    public function reopen(Task $task, Worker $worker,): Task {
        return $this->reopen->execute(
            $task,
            $worker,
        );
    }
}
