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

    public function test_live_absent_includes_only_finished_periods_worked_today(): void
    {
        $url = '/api/v1/worker-attendances/absent/live';
        $this->getJson($url)->assertUnauthorized();

        $a = DailyLog::factory()->create(['date' => '2026-11-27']);
        $b = DailyLog::factory()->for($a->company)->create(['date' => '2026-11-27']);
        $yesterday = DailyLog::factory()->for($a->company)->create(['date' => '2026-11-26']);
        $worker = Worker::factory()->for($a->company)->create();
        $idle = Worker::factory()->for($a->company)->create();
        $active = Worker::factory()->for($a->company)->create();
        $this->record($b, $worker, '18:00', '20:00');
        $second = $this->record($b, $worker, '11:00', '13:00');
        $first = $this->record($a, $worker, '08:00', '10:00');
        $this->record($yesterday, $worker, '08:00', '10:00');
        $this->record($a, $active, '11:00', null);
        Worker::factory()->create();

        Sanctum::actingAs(Worker::factory()->for($a->company)->admin()->create());
        $this->travelTo(Carbon::parse('2026-11-27 13:00', 'Europe/Belgrade'));
        $rows = $this->getJson($url)->assertOk()->assertJsonPath('data.0.date', '2026-11-27')
            ->assertJsonCount(2, 'data.0.absent_workers')->json('data.0.absent_workers');
        $workers = array_column($rows, null, 'id');
        $this->assertSame([], $workers[$idle->id]['periods']);
        $this->assertSame([$first->id, $second->id], array_column($workers[$worker->id]['periods'], 'id'));
        $this->assertSame($a->construction_site_id, $workers[$worker->id]['periods'][0]['construction_site']['id']);
        $this->assertSame($b->siteManagers()->sole()->id, $workers[$worker->id]['periods'][1]['creator']['id']);

        $legacyRows = $this->getJson('/api/v1/worker-attendances/absent?date=2026-11-27')
            ->assertOk()->assertJsonMissingPath('data.0.absent_workers.0.periods')->json('data.0.absent_workers');
        $this->assertSame(array_column($legacyRows, 'id'), array_column($rows, 'id'));
    }

    public function test_absent_uses_active_periods_today_and_daily_history_on_other_dates(): void
    {
        $log = DailyLog::factory()->create(['date' => '2026-11-26']);
        $worker = Worker::factory()->for($log->company)->create();
        $this->record($log, $worker, '08:00', '10:00');
        $this->record($log, $worker, '13:00', '17:00');
        $completed = Worker::factory()->for($log->company)->create();
        $this->record($log, $completed, '08:00', '10:00');
        $open = Worker::factory()->for($log->company)->create();
        $this->record($log, $open, '18:00', null);
        $foreign = Worker::factory()->create();
        $url = '/api/v1/worker-attendances/absent';
        Sanctum::actingAs(Worker::factory()->for($log->company)->admin()->create());

        foreach (['07:59' => true, '08:00' => false, '09:00' => false, '10:00' => true, '12:00' => true, '13:00' => false, '16:00' => false, '17:00' => true] as $time => $absent) {
            $this->travelTo(Carbon::parse("2026-11-26 $time", 'Europe/Belgrade'));
            $response = $this->getJson("$url?date=2026-11-26")->assertOk()->assertJsonPath('data.0.date', '2026-11-26');
            $ids = array_column($response->json('data.0.absent_workers'), 'id');
            $this->assertSame($absent, in_array($worker->id, $ids, true), $time);
            $this->assertNotContains($foreign->id, $ids);
        }

        $this->travelTo(Carbon::parse('2026-11-26 14:00', 'Europe/Belgrade'));
        $this->getJson("$url?date=2026-11-26")->assertOk()->assertJsonFragment(['id' => $completed->id]);
        $this->travelTo(Carbon::parse('2026-11-26 23:59:59', 'Europe/Belgrade'));
        $this->getJson("$url?date=2026-11-26")->assertOk()->assertJsonMissing(['id' => $open->id]);
        $this->travelTo(Carbon::parse('2026-11-27 00:00', 'Europe/Belgrade'));
        $this->getJson("$url?date=2026-11-27")->assertOk()->assertJsonFragment(['id' => $open->id]);

        $this->travelTo(Carbon::parse('2026-11-27 14:00', 'Europe/Belgrade'));
        $response = $this->getJson("$url?date_from=2026-11-26&date_to=2026-11-27")
            ->assertOk()->assertJsonCount(2, 'data');
        $this->assertNotContains($worker->id, array_column($response->json('data.0.absent_workers'), 'id'));
        $this->assertNotContains($completed->id, array_column($response->json('data.0.absent_workers'), 'id'));
        $this->assertNotContains($open->id, array_column($response->json('data.0.absent_workers'), 'id'));
        $past = $this->getJson("$url?date=2026-11-26")->assertOk();
        $this->assertSame($response->json('data.0'), $past->json('data.0'));
        $this->assertContains($worker->id, array_column($response->json('data.1.absent_workers'), 'id'));
    }

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
        $otherManager->constructionSites()->attach($b->construction_site_id);
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
        $outsideAssignment = $this->getJson("$url?date=2026-11-24&construction_site_id={$a->construction_site_id}")
            ->assertOk()->assertJsonCount(4, 'data')->json('data');
        $this->assertSame([[], [], [], []], array_column($outsideAssignment, 'periods'));
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
        $history = $this->getJson("$url?live=false&date=2026-11-24&worker_id={$worker->id}")
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonMissingPath('data.0.periods')
            ->assertJsonPath('data.0.construction_site.id', $a->construction_site_id)
            ->assertJsonPath('data.1.construction_site.id', $b->construction_site_id)
            ->assertJsonPath('data.1.creator.id', $b->siteManagers()->sole()->id);
        $this->assertSame([$first->id, $second->id, $later->id], array_column($history->json('data'), 'id'));
        $this->getJson("$url?date=2026-11-24")->assertOk()->assertJsonCount(5, 'data');
        $this->getJson("$url?date=2026-11-24&worker_id={$worker->id}&limit=1&offset=1")->assertOk()
            ->assertJsonPath('data.0.id', $second->id)->assertJsonCount(1, 'data');
        $historyAtSite = $this->getJson("$url?date=2026-11-24&construction_site_id={$b->construction_site_id}")
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.finished_at', null)
            ->assertJsonPath('data.0.machine_assignment_id', $assignment->id);
        $this->assertSame([$open->id, $second->id, $later->id], array_column($historyAtSite->json('data'), 'id'));
        $this->getJson("$url/by-worker?date=2026-11-24")->assertOk()
            ->assertJsonFragment(['machine_assignment_id' => $assignment->id]);
        $this->getJson("$url?at=2026-11-24T22:59:59Z")->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open->id)->assertJsonMissingPath('data.0.periods');
        $this->getJson("$url?at=2026-11-24T23:00:00Z")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("$url?live=true&date=2026-11-24")->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->getJson("$url?live=false&at=2026-11-24T13:00:00Z")->assertUnprocessable()->assertJsonValidationErrors('at');
        $this->getJson("$url?at=2026-11-24T13:00:00Z&date_from=2026-11-24")->assertUnprocessable()->assertJsonValidationErrors('date_from');

        $manager = $a->siteManagers()->sole();
        $manager->constructionSites()->attach($a->construction_site_id);
        Sanctum::actingAs($manager);
        $this->getJson("$url?live=true")->assertForbidden();
        $this->getJson("$url?date=2026-11-24")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_managers_at_the_same_site_can_read_the_same_history_without_joining_the_log(): void
    {
        $log = DailyLog::factory()->create(['date' => '2026-11-27']);
        $creator = $log->siteManagers()->sole();
        $colleague = Worker::factory()->for($log->company)->siteManager()->create();
        foreach ([$creator, $colleague] as $manager) {
            $manager->constructionSites()->attach($log->construction_site_id);
        }

        $worker = Worker::factory()->for($log->company)->create();
        $entry = $this->record($log, $worker, '08:00', '12:00');
        $otherLog = DailyLog::factory()->for($log->company)->withManager($colleague)->create(['date' => '2026-11-27']);
        $this->record($otherLog, $worker, '13:00', '17:00');
        $foreignLog = DailyLog::factory()->create(['date' => '2026-11-27']);
        $this->record($foreignLog, Worker::factory()->for($foreignLog->company)->create(), '08:00', '12:00');
        $history = '/api/v1/worker-attendances?date=2026-11-27';
        $siteHistory = "$history&construction_site_id={$log->construction_site_id}";
        $dailyAttendance = "/api/v1/daily-logs/{$log->id}/worker-attendances";

        Sanctum::actingAs($creator);
        $expected = $this->getJson($history)->assertOk()->assertJsonCount(1, 'data')->json();
        Sanctum::actingAs($colleague);
        $this->getJson($history)->assertOk()->assertExactJson($expected);
        $this->getJson($siteHistory)->assertOk()->assertExactJson($expected);
        $this->getJson("$history&construction_site_id={$otherLog->construction_site_id}")->assertOk()->assertJsonPath('data', []);
        $this->getJson($dailyAttendance)->assertOk()->assertJsonPath('data.0.id', $entry->id);
        $this->getJson("/api/v1/daily-logs/{$otherLog->id}/worker-attendances")->assertForbidden();
        $this->getJson("/api/v1/daily-logs/{$foreignLog->id}/worker-attendances")->assertNotFound();
        $this->deleteJson("$dailyAttendance/{$entry->id}", ['reason' => 'Attempt without daily log membership'])->assertForbidden();
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
