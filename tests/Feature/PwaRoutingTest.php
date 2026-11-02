<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaRoutingTest extends TestCase
{
    private string $publicDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publicDirectory = sys_get_temp_dir().'/builderlog-pwa-'.bin2hex(random_bytes(8));
        mkdir($this->publicDirectory.'/app', 0777, true);
        file_put_contents($this->publicDirectory.'/app/index.html', '<html><body>PWA routing fixture</body></html>');
        $this->app->usePublicPath($this->publicDirectory);
    }

    protected function tearDown(): void
    {
        unlink($this->publicDirectory.'/app/index.html');
        rmdir($this->publicDirectory.'/app');
        rmdir($this->publicDirectory);
        parent::tearDown();
    }

    public function test_only_app_navigations_receive_the_spa_entry(): void
    {
        foreach (['/app', '/app/', '/app/technical-fallback/path'] as $path) {
            $response = $this->get($path)->assertOk();
            $this->assertSame(realpath($this->publicDirectory.'/app/index.html'), realpath($response->baseResponse->getFile()->getPathname()));
        }
        $this->head('/app/')->assertOk();
        $this->post('/app/')->assertStatus(405);
        $this->get('/app-other')->assertNotFound();
    }

    public function test_missing_assets_are_not_replaced_with_html(): void
    {
        foreach (['/app/assets/missing.js', '/app/assets', '/app/sw.js', '/app/missing.webmanifest'] as $path) {
            $this->get($path)->assertNotFound()->assertDontSee('PWA routing fixture');
        }
    }

    public function test_api_and_routes_outside_app_are_preserved(): void
    {
        $this->get('/')->assertOk()->assertDontSee('PWA routing fixture');
        $this->getJson('/api/ping')->assertOk()->assertExactJson(['message' => 'API works!']);
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/api/v1/login', [])->assertStatus(422);
        $this->get('/api/v1/pwa-route-that-does-not-exist', ['Accept' => 'text/html'])
            ->assertNotFound()->assertHeader('Content-Type', 'application/json');
        // Laravel's existing signed local-storage route rejects unsigned requests.
        $this->get('/storage/pwa-file-that-does-not-exist')->assertForbidden();
    }

    public function test_an_unbuilt_frontend_returns_a_clear_service_unavailable_response(): void
    {
        $this->app->usePublicPath($this->publicDirectory.'/not-built');
        $this->get('/app/')->assertStatus(503);
    }
}
