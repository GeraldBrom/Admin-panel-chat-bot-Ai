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

// Публичный вебхук для ChatKit Agent (без аутентификации)
Route::post('/chatkit/webhook', [\App\Http\Controllers\ChatKitController::class, 'webhook']);

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

    // Маршруты для сценарных ботов
    Route::prefix('scenario-bots')->group(function () {
        Route::get('/', [\App\Http\Controllers\ScenarioBotController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\ScenarioBotController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\ScenarioBotController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\ScenarioBotController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\ScenarioBotController::class, 'destroy']);
        
        // Управление сессиями
        Route::post('/sessions/start', [\App\Http\Controllers\ScenarioBotController::class, 'startSession']);
        Route::delete('/sessions/{chatId}/stop', [\App\Http\Controllers\ScenarioBotController::class, 'stopSession'])->where('chatId', '.*');
        Route::post('/sessions/{chatId}/reset', [\App\Http\Controllers\ScenarioBotController::class, 'resetSession'])->where('chatId', '.*');
        Route::get('/sessions/{chatId}', [\App\Http\Controllers\ScenarioBotController::class, 'getSession'])->where('chatId', '.*');
        Route::get('/{id}/sessions', [\App\Http\Controllers\ScenarioBotController::class, 'getSessions']);
        
        // Управление шагами сценария
        Route::prefix('{botId}/steps')->group(function () {
            Route::get('/', [\App\Http\Controllers\ScenarioStepController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\ScenarioStepController::class, 'store']);
            Route::get('/{stepId}', [\App\Http\Controllers\ScenarioStepController::class, 'show']);
            Route::put('/{stepId}', [\App\Http\Controllers\ScenarioStepController::class, 'update']);
            Route::delete('/{stepId}', [\App\Http\Controllers\ScenarioStepController::class, 'destroy']);
            Route::post('/update-order', [\App\Http\Controllers\ScenarioStepController::class, 'updateOrder']);
            Route::post('/update-positions', [\App\Http\Controllers\ScenarioStepController::class, 'updatePositions']);
        });
    });

    // Маршруты для ChatKit Agent
    Route::prefix('chatkit')->group(function () {
        Route::get('/sessions', [\App\Http\Controllers\ChatKitController::class, 'index']);
        Route::post('/sessions/start', [\App\Http\Controllers\ChatKitController::class, 'start']);
        Route::post('/sessions/stop-all', [\App\Http\Controllers\ChatKitController::class, 'stopAll']);
        Route::get('/sessions/{chatId}', [\App\Http\Controllers\ChatKitController::class, 'show'])->where('chatId', '.*');
        Route::delete('/sessions/{chatId}', [\App\Http\Controllers\ChatKitController::class, 'stop'])->where('chatId', '.*');
        Route::delete('/sessions/{chatId}/clear', [\App\Http\Controllers\ChatKitController::class, 'clearSession'])->where('chatId', '.*');
    });
});
