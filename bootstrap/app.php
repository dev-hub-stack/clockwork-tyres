<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // ─── Wholesale API routes ─────────────────────────────────────────
            // Kept in a separate file for clean separation of concerns.
            // All routes prefixed /api/* — same as api.php.
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api-wholesale.php'));
        },
    )
    ->withEvents(discover: [
        __DIR__ . '/../app/Listeners',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'wholesale.auth' => \App\Http\Middleware\WholesaleAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
