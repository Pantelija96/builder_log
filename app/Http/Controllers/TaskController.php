<?php

namespace App\Http\Controllers;

use App\DTO\Task\CreateTaskData;
use App\DTO\Task\GetTasksData;
use App\DTO\Task\UpdateTaskData;
use App\Http\Requests\Task\CreateTaskRequest;
use App\Http\Requests\Task\DeleteTaskRequest;
use App\Http\Requests\Task\GetTasksRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\Worker;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends ApiController
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function index(GetTasksRequest $request): JsonResponse
    {
        return $this->success(
            TaskResource::collection(
                $this->taskService->get(
                    GetTasksData::fromRequest($request)
                )
            )
        );
    }

    public function store(CreateTaskRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $task = $this->taskService->create(
            data: CreateTaskData::fromRequest($request),
            worker: $worker,
        );

        return $this->success(
            TaskResource::make(
                $task->load([
                    'creator',
                    'siteManager',
                    'constructionSite',
                    'completedBy',
//                    'attachments',
                ])
            ),
            'Task created successfully.',
        );
    }

    public function update(Task $task, UpdateTaskRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $task = $this->taskService->update(
            task: $task,
            data: UpdateTaskData::fromRequest($request),
            worker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            TaskResource::make($task),
            'Task updated successfully.',
        );
    }

    public function destroy(Task $task, DeleteTaskRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $this->taskService->delete(
            task: $task,
            worker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Task deleted successfully.',
        );
    }

    public function myTasks(GetTasksRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            TaskResource::collection(
                $this->taskService->getMyTasks(
                    worker: $worker,
                    data: GetTasksData::fromRequest($request),
                )
            )
        );
    }

    public function read(Task $task,): JsonResponse
    {
        return $this->success(
            TaskResource::make(
                $this->taskService->markAsRead($task)
            ),
            'Task marked as read.',
        );
    }

    public function complete(Task $task,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            TaskResource::make(
                $this->taskService->complete(
                    task: $task,
                    worker: $worker,
                )
            ),
            'Task completed successfully.',
        );
    }

    public function reopen(Task $task,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            TaskResource::make(
                $this->taskService->reopen(
                    task: $task,
                    worker: $worker,
                )
            ),
            'Task reopened successfully.',
        );
    }
}
