<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Birthday SMS: send daily at 08:00 AM ─────────────────────────────────────
Schedule::command('birthday:send')->dailyAt('08:00');

// ── Birthday SMS: retry failed every 4 hours (catches recharged balances) ─────
Schedule::command('birthday:retry-failed')->everyFourHours();
