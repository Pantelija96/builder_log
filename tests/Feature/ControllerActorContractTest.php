<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Machine;
use App\Models\Subcontractor;
use App\Models\Supplier;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ControllerActorContractTest extends TestCase
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

    #[DataProvider('modules')]
    public function test_patch_preserves_actor_validation_and_company_check_order(
        string $model,
        string $endpoint,
        array $payload,
        string $field,
    ): void {
        config(['app.debug' => false]);
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = Worker::factory()->admin()->for($company)->create();
        $nonAdmin = Worker::factory()->for($company)->create();

        $target = $this->createTarget($model, $company);
        $foreignTarget = $this->createTarget($model, $otherCompany);
        if ($model === Machine::class) {
            $payload['owner_id'] = $company->id;
        }

        $url = "/api/v1/{$endpoint}/{$target->id}";
        $foreignUrl = "/api/v1/{$endpoint}/{$foreignTarget->id}";
        $originalValue = $target->{$field};
        $foreignOriginalValue = $foreignTarget->{$field};

        Sanctum::actingAs($nonAdmin);
        $this->patchJson($url, $payload)->assertForbidden();
        $this->patchJson($foreignUrl, $payload)->assertForbidden();
        $this->patchJson($url, [$field => []])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($originalValue, $target->fresh()->{$field});

        Sanctum::actingAs($admin);
        $this->patchJson($foreignUrl, $payload)->assertNotFound();
        $this->patchJson($foreignUrl, [$field => []])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->patchJson("/api/v1/{$endpoint}/999999", [$field => []])->assertNotFound();
        $this->assertSame($foreignOriginalValue, $foreignTarget->fresh()->{$field});

        $this->patchJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath("data.{$field}", $payload[$field]);
        $this->assertSame($payload[$field], $target->fresh()->{$field});

        if ($model === Worker::class) {
            $this->assertDatabaseHas('audit_logs', [
                'actor_id' => $admin->id,
                'auditable_type' => 'worker',
                'auditable_id' => $target->id,
                'event' => 'worker.log.updated',
            ]);
        }
    }

    private function createTarget(string $model, Company $company): Worker|Supplier|Subcontractor|Machine
    {
        return match ($model) {
            Worker::class => Worker::factory()->for($company)->create(),
            Machine::class => Machine::factory()->for($company)->create([
                'owner_type' => 'company',
                'owner_id' => $company->id,
            ]),
            Supplier::class, Subcontractor::class => $model::create([
                'company_id' => $company->id,
                'name' => 'Original name',
                'is_active' => true,
            ]),
        };
    }

    public static function modules(): array
    {
        return [
            'worker' => [Worker::class, 'workers', ['first_name' => 'Updated Worker'], 'first_name'],
            'supplier' => [Supplier::class, 'suppliers', ['name' => 'Updated Supplier', 'is_active' => true], 'name'],
            'subcontractor' => [
                Subcontractor::class,
                'subcontractors',
                ['name' => 'Updated Subcontractor', 'is_active' => true],
                'name',
            ],
            'machine' => [
                Machine::class,
                'machines',
                ['name' => 'Updated Machine', 'owner_type' => 'company', 'status' => 'active'],
                'name',
            ],
        ];
    }
}
