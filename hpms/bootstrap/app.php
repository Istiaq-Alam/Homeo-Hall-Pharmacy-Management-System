<?php

use App\Http\Middleware\EnsureChamberIsSelected;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Registered outside the web middleware group on purpose: database
            // sessions and cookies must not turn a database outage into a 500.
            Route::get('/health', function () {
                try {
                    DB::select('select 1');

                    return response()->json(['status' => 'ok', 'database' => 'ok']);
                } catch (Throwable) {
                    return response()->json(['status' => 'unavailable', 'database' => 'unavailable'], 503);
                }
            })->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        // Render terminates TLS at its proxy and forwards the original scheme.
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [SetLocale::class]);
        $middleware->alias([
            'chamber.selected' => EnsureChamberIsSelected::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
