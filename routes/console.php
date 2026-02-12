<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check Copilot usage for all users every hour
Schedule::command('copilot:check-usage')
    ->hourly()
    ->withoutOverlapping()
    ->onSuccess(fn() => Log::info('[copilot:check-usage] scheduled job completed successfully'))
    ->onFailure(fn() => Log::error('[copilot:check-usage] scheduled job failed'));
