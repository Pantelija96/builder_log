<?php

namespace Tests\Feature;

use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthSessionContractTest extends TestCase
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

    public function test_login_issues_a_bearer_token_accepted_by_me(): void
    {
        $worker = Worker::factory()->create();

        $response = $this->postJson('/api/v1/login', [
            'username' => $worker->username,
            'password' => 'password',
        ])->assertOk();

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $response->assertExactJson([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'worker' => [
                    'id' => $worker->id,
                    'first_name' => $worker->first_name,
                    'last_name' => $worker->last_name,
                    'full_name' => $worker->full_name,
                    'username' => $worker->username,
                    'role' => 'worker',
                    'company' => ['id' => $worker->company_id, 'name' => $worker->company->name],
                ],
            ],
        ]);
        $this->assertSame(['api-session'], $worker->tokens()->pluck('name')->all());

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.id', $worker->id)
            ->assertJsonPath('data.company.id', $worker->company_id);
    }

    public function test_invalid_credentials_keep_the_existing_error_response(): void
    {
        $worker = Worker::factory()->create();

        $this->postJson('/api/v1/login', [
            'username' => $worker->username,
            'password' => 'incorrect',
        ])->assertUnprocessable()->assertExactJson([
            'success' => false,
            'message' => 'Invalid credentials.',
            'errors' => null,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[DataProvider('logoutCases')]
    public function test_logout_revokes_only_the_expected_tokens(int $tokenCount, bool $allDevices): void
    {
        $worker = Worker::factory()->create();
        $first = $worker->createToken('first device');
        $second = $tokenCount === 2 ? $worker->createToken('second device') : null;
        $otherWorker = Worker::factory()->create();
        $other = $otherWorker->createToken('another worker');
        $firstHeader = ['Authorization' => 'Bearer '.$first->plainTextToken];

        $this->postJson($allDevices ? '/api/v1/logout-all' : '/api/v1/logout', [], $firstHeader)
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => $allDevices ? 'Logged out from all devices.' : 'Logout successful.',
                'data' => null,
            ]);

        $remaining = ! $allDevices && $second !== null ? [$second->accessToken->id] : [];
        $this->assertSame($remaining, $worker->tokens()->pluck('id')->all());
        $this->assertSame([$other->accessToken->id], $otherWorker->tokens()->pluck('id')->all());

        // Each request must resolve its bearer token again instead of reusing the cached guard user.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', $firstHeader)->assertUnauthorized();

        if ($second !== null) {
            $this->app['auth']->forgetGuards();
            $response = $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$second->plainTextToken]);
            if ($allDevices) {
                $response->assertUnauthorized();
            } else {
                $response->assertOk()->assertJsonPath('data.id', $worker->id);
            }
        }

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$other->plainTextToken])
            ->assertOk()->assertJsonPath('data.id', $otherWorker->id);
    }

    public static function logoutCases(): array
    {
        return [
            'single token' => [1, false],
            'current token only' => [2, false],
            'all tokens for the current worker' => [2, true],
        ];
    }
}
