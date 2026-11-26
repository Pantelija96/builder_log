<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLiveAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_periods_are_grouped_by_worker_and_keep_each_entries_creator(): void
    {
        $url = '/api/v1/worker-attendances/by-worker';
        $this->getJson("$url?date=2026-11-24")->assertUnauthorized();

        $a = DailyLog::factory()->create(['date' => '2026-11-24']);
        $b = DailyLog::factory()->for($a->company)->create(['date' => '2026-11-24']);
        $c = DailyLog::factory()->for($a->company)->create(['date' => '2026-11-24']);
        $worker = Worker::factory()->for($a->company)->create();
        $secondWorker = Worker::factory()->for($a->company)->create();
        $idleWorker = Worker::factory()->for($a->company)->create();
        $inactiveOperator = Worker::factory()->for($a->company)->operator()->create(['is_active' => false]);
        $otherManager = Worker::factory()->for($a->company)->siteManager()->create();
        $managerB = $b->siteManagers()->sole();

        $last = $this->record($c, $worker, '18:00', null);
        $first = $this->record($a, $worker, '07:00', '12:00');
        $middle = $this->record($b, $worker, '13:00', '17:00');
        $middle->update(['created_by' => $otherManager->id]);
        $otherPeriod = $this->record($b, $secondWorker, '09:00', '11:00');
        $b->siteManagers()->attach($otherManager);
        $next = DailyLog::factory()->for($a->company)->create(['date' => '2026-11-25']);
        $this->record($next, $worker, '12:00', '13:00');
        $foreign = DailyLog::factory()->create(['date' => '2026-11-24']);
        $this->record($foreign, Worker::factory()->for($foreign->company)->create(), '08:00', null);

        Sanctum::actingAs(Worker::factory()->for($a->company)->admin()->create());
        $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->getJson("$url?date=2026-02-30")->assertUnprocessable()->assertJsonValidationErrors('date');
        $noAttendance = $this->getJson("$url?date=2026-11-23")->assertOk()->assertJsonCount(4, 'data')->json('data');
        $this->assertSame([[], [], [], []], array_column($noAttendance, 'periods'));
        $response = $this->getJson("$url?date=2026-11-24")->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.worker.id', $worker->id)
            ->assertJsonPath('data.0.worker_id', $worker->id)
            ->assertJsonPath('data.0.periods.0.construction_site.id', $a->construction_site_id)
            ->assertJsonPath('data.0.periods.1.construction_site.name', $b->constructionSite->name)
            ->assertJsonPath('data.0.periods.1.site_manager_id', $managerB->id)
            ->assertJsonPath('data.0.periods.1.creator.id', $otherManager->id)
            ->assertJsonPath('data.0.periods.1.created_by', $otherManager->id)
            ->assertJsonPath('data.0.periods.2.finished_at', null)
            ->assertJsonPath('data.1.worker_id', $secondWorker->id)
            ->assertJsonPath('data.1.periods.0.id', $otherPeriod->id);
        $this->assertSame([$first->id, $middle->id, $last->id], array_column($response->json('data.0.periods'), 'id'));
        $roster = array_column($response->json('data'), null, 'worker_id');
        $this->assertSame([], $roster[$idleWorker->id]['periods']);
        $this->assertFalse($roster[$inactiveOperator->id]['worker']['is_active']);

        $site = $b->construction_site_id;
        $atSite = $this->getJson("$url?date=2026-11-24&construction_site_id=$site")
            ->assertOk()->assertJsonCount(4, 'data')->json('data');
        $byWorker = array_column($atSite, 'periods', 'worker_id');
        $this->assertSame([$middle->id], array_column($byWorker[$worker->id], 'id'));
        $this->assertSame([$otherPeriod->id], array_column($byWorker[$secondWorker->id], 'id'));
        $this->assertSame([], $byWorker[$idleWorker->id]);

        $byAttribution = $this->getJson("$url?date=2026-11-24&site_manager_id={$managerB->id}")
            ->assertOk()->assertJsonCount(4, 'data')->json('data');
        $this->assertSame([$otherPeriod->id, $middle->id], array_column(array_merge(...array_column($byAttribution, 'periods')), 'id'));
        $combined = $this->getJson("$url?date=2026-11-24&construction_site_id={$a->construction_site_id}&site_manager_id={$managerB->id}")
            ->assertOk()->assertJsonCount(4, 'data')->json('data');
        $this->assertSame([[], [], [], []], array_column($combined, 'periods'));
        $this->getJson("$url?date=2026-11-24&construction_site_id={$foreign->construction_site_id}")
            ->assertUnprocessable()->assertJsonValidationErrors('construction_site_id');
        $this->getJson("$url?date=2026-11-24&site_manager_id={$foreign->siteManagers()->sole()->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('site_manager_id');

        Sanctum::actingAs($otherManager);
        $this->getJson("$url?date=2026-11-24")->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonCount(1, 'data.1.periods')
            ->assertJsonPath('data.1.periods.0.id', $middle->id);
        $outsideMembership = $this->getJson("$url?date=2026-11-24&construction_site_id={$a->construction_site_id}")
            ->assertOk()->assertJsonCount(4, 'data')->json('data');
        $this->assertSame([[], [], [], []], array_column($outsideMembership, 'periods'));
    }

    public function test_live_and_historical_modes_preserve_scope_and_attendance_rows(): void
    {
        $a = DailyLog::factory()->create(['date' => '2026-11-24']);
        $b = DailyLog::factory()->for($a->company)->create(['date' => '2026-11-24']);
        $worker = Worker::factory()->for($a->company)->create();
        $first = $this->record($a, $worker, '08:00', '12:00');
        $second = $this->record($b, $worker, '13:00', '17:00');
        $later = $this->record($b, $worker, '17:00', '20:00');
        $this->record($a, Worker::factory()->for($a->company)->create(), '16:00', '20:00');

        $operator = Worker::factory()->for($a->company)->operator()->create();
        $machine = Machine::factory()->for($a->company)->create(['owner_type' => 'company', 'owner_id' => $a->company_id]);
        $assignment = MachineAssignment::create($this->context($b, $operator) + ['machine_id' => $machine->id]);
        $open = $this->record($b, $operator, '12:00', null, $assignment->id);

        $foreign = DailyLog::factory()->create(['date' => '2026-11-24']);
        $this->record($foreign, Worker::factory()->for($foreign->company)->create(), '08:00', null);
        Sanctum::actingAs(Worker::factory()->for($a->company)->admin()->create());
        $url = '/api/v1/worker-attendances';
        $this->travelTo(Carbon::parse('2026-11-24 11:59', 'Europe/Belgrade'));
        $this->getJson("$url?live=true")->assertOk()->assertJsonPath('data.0.id', $first->id)->assertJsonCount(1, 'data');

        $this->getJson($url.'?'.http_build_query(['at' => '2026-11-24T12:00:00+01:00']))
            ->assertOk()->assertJsonPath('data.0.id', $open->id)->assertJsonCount(1, 'data');
        $this->travelTo(Carbon::parse('2026-11-24 14:00', 'Europe/Belgrade'));
        $this->getJson("$url?live=true")->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.machine_assignment_id', $assignment->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.1.worker.id', $worker->id)
            ->assertJsonPath('data.1.construction_site.id', $b->construction_site_id);
        $this->getJson("$url?live=true&construction_site_id={$a->construction_site_id}")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("$url?live=true&construction_site_id={$b->construction_site_id}&limit=1&offset=1")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);
        $this->getJson("$url?live=false&date=2026-11-24&worker_id={$worker->id}")->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $second->id)->assertJsonPath('data.2.id', $later->id);
        $this->getJson("$url?date=2026-11-24")->assertOk()->assertJsonCount(5, 'data');
        $this->getJson("$url/by-worker?date=2026-11-24")->assertOk()
            ->assertJsonFragment(['machine_assignment_id' => $assignment->id]);
        $this->getJson("$url?at=2026-11-24T22:59:59Z")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $open->id);
        $this->getJson("$url?at=2026-11-24T23:00:00Z")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("$url?live=true&date=2026-11-24")->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->getJson("$url?live=false&at=2026-11-24T13:00:00Z")->assertUnprocessable()->assertJsonValidationErrors('at');
        $this->getJson("$url?at=2026-11-24T13:00:00Z&date_from=2026-11-24")->assertUnprocessable()->assertJsonValidationErrors('date_from');

        Sanctum::actingAs($a->siteManagers()->sole());
        $this->getJson("$url?live=true")->assertForbidden();
        $this->getJson("$url?date=2026-11-24")->assertOk()->assertJsonCount(2, 'data');
    }

    private function context(DailyLog $log, Worker $worker): array
    {
        $manager = $log->siteManagers()->sole();

        return [
            'company_id' => $log->company_id, 'daily_log_id' => $log->id, 'construction_site_id' => $log->construction_site_id,
            'worker_id' => $worker->id, 'site_manager_id' => $manager->id, 'created_by' => $manager->id, 'date' => $log->date->toDateString(),
        ];
    }

    private function record(DailyLog $log, Worker $worker, string $start, ?string $finish, ?int $assignment = null): WorkerAttendance
    {
        $context = $this->context($log, $worker);

        return WorkerAttendance::create($context + [
            'started_at' => $context['date'].' '.$start,
            'finished_at' => $finish === null ? null : $context['date'].' '.$finish,
            'machine_assignment_id' => $assignment,
        ]);
    }
}
