<?php

namespace Tests\Feature;

use App\Enums\WorkerRole;
use App\Models\ConstructionSite;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkerDisplayOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_defaults_and_patch_preserves_omitted_order(): void
    {
        $admin = Worker::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $defaultId = $this->postJson('/api/v1/workers', $this->payload('ana'))
            ->assertOk()->assertJsonPath('data.display_order', 0)->json('data.id');
        $customId = $this->postJson('/api/v1/workers', $this->payload('marko') + ['display_order' => 5])
            ->assertOk()->assertJsonPath('data.display_order', 5)->json('data.id');
        $this->postJson('/api/v1/workers', $this->payload('jovan') + ['display_order' => 1])
            ->assertOk()->assertJsonPath('data.display_order', 1);
        $this->postJson('/api/v1/workers', $this->payload('petar') + ['display_order' => 0])
            ->assertOk()->assertJsonPath('data.display_order', 0);
        $this->postJson('/api/v1/workers', $this->payload('nikola') + ['display_order' => 1])
            ->assertOk()->assertJsonPath('data.display_order', 1);
        $this->assertDatabaseHas('workers', ['id' => $customId, 'display_order' => 5]);

        $this->patchJson("/api/v1/workers/$defaultId", ['display_order' => 3])
            ->assertOk()->assertJsonPath('data.display_order', 3);
        $this->patchJson("/api/v1/workers/$defaultId", ['last_name' => 'Ivić'])
            ->assertOk()->assertJsonPath('data.display_order', 3);
        $this->getJson("/api/v1/workers/$defaultId")->assertOk()->assertJsonPath('data.display_order', 3);
        $this->assertDatabaseHas('workers', ['id' => $defaultId, 'display_order' => 3]);
        $this->patchJson("/api/v1/workers/$defaultId", ['display_order' => 0])
            ->assertOk()->assertJsonPath('data.display_order', 0);
        $this->assertDatabaseHas('workers', ['id' => $defaultId, 'display_order' => 0]);
    }

    public static function invalidValues(): array
    {
        return [[-1], [1.5], ['high'], [true], [null], [2147483648]];
    }

    #[DataProvider('invalidValues')]
    public function test_create_and_patch_reject_invalid_order(mixed $order): void
    {
        $admin = Worker::factory()->admin()->create();
        $worker = Worker::factory()->for($admin->company)->create(['display_order' => 3]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/workers', $this->payload('candidate') + ['display_order' => $order])
            ->assertUnprocessable()->assertJsonValidationErrors('display_order');
        $this->patchJson("/api/v1/workers/{$worker->id}", ['display_order' => $order])
            ->assertUnprocessable()->assertJsonValidationErrors('display_order');
        $this->assertSame(3, $worker->fresh()->display_order);
    }

    public static function roles(): array
    {
        return [[WorkerRole::WORKER], [WorkerRole::OPERATOR], [WorkerRole::DRIVER]];
    }

    #[DataProvider('roles')]
    public function test_lists_use_display_order_and_alphabetical_ties_for_each_role(WorkerRole $role): void
    {
        $admin = Worker::factory()->admin()->create();
        $factory = Worker::factory()->for($admin->company)->state(['role' => $role]);
        $c = $factory->create(['first_name' => 'Cedomir', 'display_order' => 3]);
        $marko = $factory->create(['first_name' => 'Marko', 'last_name' => 'Adamic', 'display_order' => 1]);
        $jovan = $factory->create(['first_name' => 'Jovan', 'last_name' => 'Jovic', 'display_order' => 1]);
        $ana = $factory->create(['first_name' => 'Ana', 'last_name' => 'Zoric', 'display_order' => 1]);
        $otherAna = $factory->create(['first_name' => 'Ana', 'last_name' => 'Ivic', 'display_order' => 1]);
        $b = $factory->create(['first_name' => 'Bojan', 'display_order' => 2]);
        $z = $factory->create(['first_name' => 'Zoran', 'display_order' => 0]);
        $zeroAna = $factory->create(['first_name' => 'Ana', 'last_name' => 'Ivic', 'display_order' => 0]);
        $sameAna = $factory->create(['first_name' => 'Ana', 'last_name' => 'Ivic', 'display_order' => 0]);
        Sanctum::actingAs($admin);

        $ids = [$otherAna->id, $ana->id, $jovan->id, $marko->id, $b->id, $c->id, $zeroAna->id, $sameAna->id, $z->id];
        $this->assertSame($ids, array_column($this->getJson("/api/v1/workers?role={$role->value}")
            ->assertOk()->json('data'), 'id'));
        $this->assertSame($ids, array_column($this->getJson("/api/v1/workers/available?role={$role->value}")
            ->assertOk()->json('data'), 'id'));
        $this->assertSame([$zeroAna->id, $z->id], array_column(
            $this->getJson("/api/v1/workers?role={$role->value}&sort=id&direction=desc&offset=1&limit=2")
                ->assertOk()->json('data'), 'id'));
    }

    public function test_assigned_managers_use_the_same_display_order(): void
    {
        $admin = Worker::factory()->admin()->create();
        $site = ConstructionSite::factory()->for($admin->company)->create();
        $factory = Worker::factory()->for($admin->company)->siteManager();
        $ana = $factory->create(['first_name' => 'Ana', 'display_order' => 0]);
        $marko = $factory->create(['first_name' => 'Marko', 'display_order' => 1]);
        $jovan = $factory->create(['first_name' => 'Jovan', 'display_order' => 1]);
        $site->siteManagers()->attach([$ana->id, $marko->id, $jovan->id]);
        Sanctum::actingAs($admin);

        $expected = [$jovan->id, $marko->id, $ana->id];
        $assigned = $this->getJson("/api/v1/construction-sites/{$site->id}/site-managers")->assertOk()->json('data');
        $this->assertSame($expected, array_column($assigned, 'id'));
        $sites = $this->getJson('/api/v1/construction-sites')->assertOk()->json('data');
        $this->assertSame($expected, array_column($sites[0]['site_managers'], 'id'));
    }

    private function payload(string $username): array
    {
        return [
            'first_name' => ucfirst($username), 'last_name' => 'Petrovic', 'role' => 'worker',
            'username' => $username, 'password' => 'test-password',
        ];
    }
}
