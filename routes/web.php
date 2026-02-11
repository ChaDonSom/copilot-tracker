<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/login', [AuthController::class, 'redirectToGithub'])->name('login');
Route::get('/login/github', [AuthController::class, 'redirectToGithub'])->name('login.github');
Route::get('/login/github/callback', [AuthController::class, 'handleGithubCallback']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/refresh', [\App\Http\Controllers\DashboardController::class, 'refresh'])->name('dashboard.refresh');
    Route::get('/dashboard/chart-data', [\App\Http\Controllers\DashboardController::class, 'chartData'])->name('dashboard.chart-data');
});
