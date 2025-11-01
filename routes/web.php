<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GreenApiWebhookController;

// Webhook Green API (должен быть ПЕРЕД catch-all маршрутом)
Route::post('/green-api/webhook', [GreenApiWebhookController::class, 'handle']);
Route::match(['get', 'post'], '/green-api/webhook/test', [GreenApiWebhookController::class, 'test']);

// Login route (для редиректов Laravel)
Route::get('/login', function () {
    return view('index');
})->name('login');

// Все маршруты отдают index.blade.php, и Vue Router обрабатывает навигацию
Route::get('/{any}', function () {
    return view('index');
})->where('any', '.*');
