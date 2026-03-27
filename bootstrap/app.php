<?php

use Filament\Actions\Exceptions\ActionNotResolvableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

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
        $staleLivewireState = static function (Request $request) {
            if (! $request->hasHeader('X-Livewire')) {
                return null;
            }

            if (! $request->route()?->named('*livewire.update')) {
                return null;
            }

            $request->session()->forget('filament.notifications');

            return response('Livewire state expired.', 419);
        };

        $exceptions->render(function (CannotUpdateLockedPropertyException $exception, Request $request) use ($staleLivewireState) {
            return $staleLivewireState($request);
        });

        $exceptions->render(function (CorruptComponentPayloadException $exception, Request $request) use ($staleLivewireState) {
            return $staleLivewireState($request);
        });

        $exceptions->render(function (ActionNotResolvableException $exception, Request $request) use ($staleLivewireState) {
            return $staleLivewireState($request);
        });

        $exceptions->render(function (TypeError $exception, Request $request) use ($staleLivewireState) {
            $message = $exception->getMessage();

            if (! str_contains($message, 'Filament\\Notifications\\Notification::fromArray') && ! str_contains($message, '$isFilamentNotificationsComponent')) {
                return null;
            }

            return $staleLivewireState($request);
        });
    })->create();
