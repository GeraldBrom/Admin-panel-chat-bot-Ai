<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\BotConfigController;
use App\Http\Controllers\GreenApiWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Публичные маршруты авторизации
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Публичный вебхук от Green API (без аутентификации)
Route::post('/greenapi/webhook', [GreenApiWebhookController::class, 'handle']);
Route::get('/greenapi/last', [GreenApiWebhookController::class, 'last']);
Route::match(['get', 'post'], '/greenapi/webhook/test', [GreenApiWebhookController::class, 'test']);

// Защищенные маршруты (требуют авторизации)
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutFromAllDevices']);
        Route::get('/me', [AuthController::class, 'me']);
    });
    
    // Bot management routes
    Route::prefix('bots')->group(function () {
        Route::get('/', [BotController::class, 'index']);
        Route::post('/start', [BotController::class, 'start']);
        Route::post('/stop-all', [BotController::class, 'stopAll']);
        Route::get('/{chatId}', [BotController::class, 'show'])->where('chatId', '.*');
        Route::delete('/{chatId}', [BotController::class, 'stop'])->where('chatId', '.*');
    });

    // Bot configs routes (без статуса "активная" — конфигурации выбираются явно)
    Route::prefix('bot-configs')->group(function () {
        Route::get('/', [BotConfigController::class, 'index']);
        Route::post('/', [BotConfigController::class, 'store']);
        Route::get('/{id}', [BotConfigController::class, 'show']);
        Route::put('/{id}', [BotConfigController::class, 'update']);
        Route::delete('/{id}', [BotConfigController::class, 'destroy']);
    });
});

