<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Для API запросов возвращаем JSON вместо редиректа
        $middleware->redirectGuestsTo(fn () => response()->json([
            'message' => 'Unauthenticated.'
        ], 401));
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Polling для локальной разработки (на продакшене используется webhook)
        // Для запуска: php artisan schedule:work
        $schedule->command('greenapi:poll --minutes=1')->everyTenSeconds();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
