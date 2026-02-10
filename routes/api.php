<?php

use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.github')->group(function () {
    Route::get('/usage', [UsageController::class, 'show']);
    Route::post('/usage/refresh', [UsageController::class, 'refresh']);
    Route::get('/usage/today', [UsageController::class, 'today']);
    Route::get('/usage/history', [UsageController::class, 'history']);
});
