<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\BotConfigController;
use App\Http\Controllers\GreenApiWebhookController;
use App\Http\Controllers\LogController;
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

// Защищенные маршруты (требуют авторизации)
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutFromAllDevices']);
        Route::get('/me', [AuthController::class, 'me']);
    });
    
    
    Route::prefix('bots')->group(function () {
        Route::get('/', [BotController::class, 'index']);
        Route::post('/start', [BotController::class, 'start']);
        Route::post('/stop-all', [BotController::class, 'stopAll']);
        
        Route::delete('/{chatId}/session', [BotController::class, 'clearSession'])->where('chatId', '.*');
        Route::get('/{chatId}', [BotController::class, 'show'])->where('chatId', '.*');
        Route::delete('/{chatId}', [BotController::class, 'stop'])->where('chatId', '.*');
    });

    
    Route::prefix('bot-configs')->group(function () {
        Route::get('/', [BotConfigController::class, 'index']);
        Route::post('/', [BotConfigController::class, 'store']);
        Route::put('/{id}', [BotConfigController::class, 'update']);
        Route::delete('/{id}', [BotConfigController::class, 'destroy']);
    });

    
    Route::prefix('logs')->group(function () {
        Route::get('/', [LogController::class, 'index']);
        Route::get('/download', [LogController::class, 'download']);
        Route::post('/clear', [LogController::class, 'clear']);
    });
});
