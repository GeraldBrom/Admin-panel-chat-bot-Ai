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
        // Исключаем webhook endpoints из CSRF проверки
        $middleware->validateCsrfTokens(except: [
            '/green-api/webhook',
            '/green-api/webhook/*',
            '/api/greenapi/webhook',
            '/api/greenapi/webhook/*',
        ]);
        
        // Добавляем middleware для установки charset в JSON ответах
        $middleware->append(\App\Http\Middleware\SetJsonCharset::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Polling для локальной разработки (на продакшене используется webhook)
        // Для запуска: php artisan schedule:work
        $schedule->command('greenapi:poll --minutes=1')->everyTenSeconds();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Обработка неавторизованных запросов для API
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }
        });
    })->create();
