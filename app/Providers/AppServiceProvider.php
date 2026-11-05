<?php

namespace App\Providers;

use App\Listeners\RecordSuccessfulLogin;
use App\Services\Har\ManualWorkOrderSource;
use App\Services\Har\WorkOrderSource;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // HAR Work Order / Service Request data comes from the manual tables for
        // now; swap this binding for WpcWorkOrderSource when the WPC integration
        // is built — no controller or report changes needed.
        $this->app->bind(WorkOrderSource::class, ManualWorkOrderSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Event::listen(Login::class, RecordSuccessfulLogin::class);

        $this->configureSsr();
    }

    /**
     * Render client-side in local development, where the Vite dev server's
     * SSR endpoint can stall a request past max_execution_time; elsewhere
     * keep config('inertia.ssr.enabled').
     */
    protected function configureSsr(): void
    {
        Inertia::disableSsr(fn (): bool => ! config('inertia.ssr.enabled', true)
            || ($this->app->isLocal() && ! config('inertia.ssr.local', false)));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
