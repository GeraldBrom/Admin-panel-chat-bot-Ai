<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GreenApiWebhookController;

// Webhook Green API (должен быть ПЕРЕД catch-all маршрутом)
Route::post('/green-api/webhook', [GreenApiWebhookController::class, 'handle']);

// Все маршруты отдают index.blade.php, и Vue Router обрабатывает навигацию
Route::get('/{any}', function () {
    return view('index');
})->where('any', '.*');
