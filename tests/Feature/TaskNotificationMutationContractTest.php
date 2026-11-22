<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Worker;
use App\Services\WorkerPushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskNotificationMutationContractTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
        DB::purge('sqlite');
    }

    public function test_read_complete_reopen_and_delete_preserve_notification_scope_and_effects(): void
    {
        $this->mock(WorkerPushNotificationService::class)->shouldReceive('send')->once();
        $now = $this->freezeSecond();
        $admin = Worker::factory()->admin()->create();
        $worker = Worker::factory()->siteManager()->for($admin->company)->create();
        $otherWorker = Worker::factory()->siteManager()->for($admin->company)->create();
        $attributes = ['company_id' => $admin->company_id, 'created_by' => $admin->id, 'worker_id' => $worker->id];
        $task = Task::factory()->create($attributes);
        $otherTask = Task::factory()->create($attributes);

        $mine = $this->notification($worker, $task);
        $otherWorkerTask = $this->notification($otherWorker, $task);
        $unrelatedTask = $this->notification($worker, $otherTask);
        $mineNonTask = $this->notification($worker, $task, NotificationType::NOTE);
        $otherNonTask = $this->notification($otherWorker, $task, NotificationType::NOTE);
        $archived = $this->notification($worker, $task);
        $archived->delete();

        Sanctum::actingAs($worker);
        $countUrl = '/api/v1/notifications/unread-count';
        $url = "/api/v1/my/tasks/{$task->id}";
        $this->getJson($countUrl)->assertOk()->assertJsonPath('data.count', 2);
        $this->patchJson("$url/read")->assertOk()->assertJsonPath('message', 'Task marked as read.');
        self::assertEquals($now, $mine->fresh()->read_at);
        self::assertEquals($now, $mineNonTask->fresh()->read_at);
        self::assertNull($otherWorkerTask->fresh()->read_at);
        self::assertNull($unrelatedTask->fresh()->read_at);
        $this->getJson($countUrl)->assertOk()->assertJsonPath('data.count', 1);

        $this->travel(1)->minutes();
        $this->patchJson("$url/complete")->assertOk()->assertJsonPath('message', 'Task completed successfully.');
        self::assertEquals(now(), $otherWorkerTask->fresh()->read_at);
        self::assertEquals($now, $mine->fresh()->read_at);
        self::assertNull($otherNonTask->fresh()->read_at);
        self::assertNull($unrelatedTask->fresh()->read_at);
        self::assertSame($worker->id, $task->fresh()->completed_by);
        $completion = Notification::query()->where('task_id', $task->id)->where('worker_id', $admin->id)->sole();
        self::assertEquals(now(), $completion->read_at);
        self::assertSame($worker->id, $completion->payload['completed_by']);

        $this->patchJson("$url/reopen")->assertOk()->assertJsonPath('message', 'Task reopened successfully.');
        self::assertNull($task->fresh()->completed_at);
        self::assertNull($task->fresh()->completed_by);
        $this->assertDatabaseCount('notifications', 7);
        $this->getJson($countUrl)->assertOk()->assertJsonPath('data.count', 1);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/tasks/{$task->id}", ['reason' => 'Contract'])->assertOk();
        $this->assertSoftDeleted($task);
        foreach ([$mine, $otherWorkerTask, $completion, $archived] as $deleted) {
            $this->assertDatabaseMissing('notifications', ['id' => $deleted->id]);
        }
        foreach ([$unrelatedTask, $mineNonTask, $otherNonTask] as $retained) {
            $this->assertNotSoftDeleted($retained);
        }
        $this->assertDatabaseCount('notifications', 3);
    }

    private function notification(Worker $worker, Task $task, NotificationType $type = NotificationType::TASK): Notification
    {
        return Notification::create([
            'company_id' => $worker->company_id,
            'worker_id' => $worker->id,
            'task_id' => $task->id,
            'type' => $type,
            'title' => 'Contract notification',
            'message' => 'Contract notification',
        ]);
    }
}
