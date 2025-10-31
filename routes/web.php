<?php

use Illuminate\Support\Facades\Route;

// Все маршруты отдают index.blade.php, и Vue Router обрабатывает навигацию
Route::get('/{any}', function () {
    return view('index');
})->where('any', '.*');
