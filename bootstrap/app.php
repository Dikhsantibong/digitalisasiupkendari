<?php

use App\Http\Middleware\EnsureOperatorPengusahaanUnit;
use App\Http\Middleware\EnsureReadOnlyAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleImpersonation;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureUserIsActive::class,
            HandleImpersonation::class,
            EnsureReadOnlyAccess::class,
            EnsureOperatorPengusahaanUnit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Inertia navigation after session expired: flash a clear notice and return to login.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->header('X-Inertia') && ! $request->expectsJson()) {
                $request->session()->flash(
                    'session_expired',
                    'Sesi Anda telah berakhir. Silakan masuk kembali untuk melanjutkan.',
                );

                return redirect()->guest(route('login'));
            }

            return null;
        });

        // CSRF / Page Expired (419): never leave the user stuck on a raw 419 error modal.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() === 419 && ! $request->expectsJson()) {
                if ($request->routeIs('logout')) {
                    return redirect()->route('login');
                }

                if ($request->user() !== null && ! $request->routeIs('login*')) {
                    Inertia::flash('toast', [
                        'type' => 'warning',
                        'message' => 'Halaman telah kedaluwarsa. Silakan kirim ulang.',
                    ]);

                    return back();
                }

                $message = $request->routeIs('login*')
                    ? 'Halaman login telah kedaluwarsa. Silakan masuk kembali.'
                    : 'Sesi Anda telah berakhir. Silakan masuk kembali untuk melanjutkan.';

                return redirect()->route('login')->with('session_expired', $message);
            }

            return $response;
        });
    })->create();
