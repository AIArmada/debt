<?php

use App\Http\Middleware\DisableAuthzImpersonationRoutes;
use App\Http\Middleware\HandleApiToken;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('charges:generate-due')->dailyAt('08:00');
        $schedule->command('reminders:send-due')->dailyAt('08:00');
        $schedule->command('support-access:expire')->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestForgery(except: ['webhooks/payments/*']);
        $middleware->append(SecurityHeaders::class);
        $middleware->prepend(DisableAuthzImpersonationRoutes::class);
        $middleware->web(append: [SetLocale::class]);
        $middleware->alias(['api.token' => HandleApiToken::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidSignatureException $exception, Request $request): ?Response {
            if ($request->routeIs('attachments.download', 'profile.exports.download')) {
                return response()->make('Not Found', 404);
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
