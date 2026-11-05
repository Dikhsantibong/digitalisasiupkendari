<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * Local development renders client-side: the Vite dev server's SSR endpoint
 * can stall a request past max_execution_time. Other environments keep SSR.
 */
class InertiaSsrTest extends TestCase
{
    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Pretend the Vite dev server runs, so SSR would go to /__inertia_ssr.
        $this->hotFile = tempnam(sys_get_temp_dir(), 'hot');
        file_put_contents($this->hotFile, 'http://[::1]:5173');
        Vite::useHotFile($this->hotFile);

        Http::fake(['*/__inertia_ssr' => Http::response(['head' => [], 'body' => '<div id="app">ssr</div>'])]);
    }

    protected function tearDown(): void
    {
        @unlink($this->hotFile);

        parent::tearDown();
    }

    public function test_local_development_skips_server_side_rendering(): void
    {
        $this->app['env'] = 'local';

        $this->get(route('login'))->assertOk();

        Http::assertNothingSent();
    }

    public function test_local_development_can_opt_back_in(): void
    {
        $this->app['env'] = 'local';
        config(['inertia.ssr.local' => true]);

        $this->get(route('login'))->assertOk();

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/__inertia_ssr'));
    }

    public function test_other_environments_keep_server_side_rendering(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('ssr', false);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/__inertia_ssr'));
    }

    public function test_the_ssr_enabled_config_still_switches_it_off(): void
    {
        config(['inertia.ssr.enabled' => false]);

        $this->get(route('login'))->assertOk();

        Http::assertNothingSent();
    }
}
