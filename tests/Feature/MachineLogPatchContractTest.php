<?php

namespace Tests\Feature;

use App\Models\ConstructionSite;
use App\Models\ExcavatorLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Services\WorkerPushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MachineLogPatchContractTest extends TestCase
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

    #[DataProvider('machineLogs')]
    public function test_partial_patch_keeps_omitted_values_and_current_null_conversion(string $type, string $model, ?float $nullFuel): void
    {
        $this->mock(WorkerPushNotificationService::class)->shouldNotReceive('send');

        $admin = Worker::factory()->admin()->create();
        $machine = Machine::factory()->for($admin->company)->create([
            'type' => $type,
            'owner_type' => 'company',
            'owner_id' => $admin->company_id,
        ]);
        $assignment = MachineAssignment::create([
            'company_id' => $admin->company_id,
            'machine_id' => $machine->id,
            'worker_id' => $admin->id,
            'date' => '2026-02-01',
            'created_by' => $admin->id,
            'construction_site_id' => ConstructionSite::factory()->for($admin->company)->create()->id,
        ]);
        $log = $model::create([
            'machine_assignment_id' => $assignment->id,
            'worker_id' => $admin->id,
            'created_by' => $admin->id,
            'fuel_added' => 18,
            'fuel_remaining' => 9,
            'note_site_manager' => 'Original',
        ]);
        Sanctum::actingAs($admin);
        $url = "/api/v1/{$type}-logs/{$log->id}";

        $this->patchJson($url, ['finished' => false, 'started' => false])
            ->assertOk()->assertJsonPath('data.id', $log->id);
        $this->assertEquals(18, $log->fresh()->fuel_added);

        $this->patchJson($url, ['fuel_added' => false])->assertUnprocessable()->assertJsonValidationErrors('fuel_added');
        $this->patchJson($url, ['fuel_added' => '', 'fuel_remaining' => '0', 'note_site_manager' => ''])
            ->assertOk()->assertJsonPath('data.note_site_manager', null);
        $fresh = $log->fresh();
        $this->assertSame($nullFuel, $fresh->fuel_added === null ? null : (float) $fresh->fuel_added);
        $this->assertEquals(0, $fresh->fuel_remaining);
    }

    public static function machineLogs(): array
    {
        return [
            'excavator' => ['excavator', ExcavatorLog::class, 0.0],
            'truck' => ['truck', TruckLog::class, null],
        ];
    }
}
