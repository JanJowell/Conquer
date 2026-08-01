<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:send-scheduled')->everyMinute();
Schedule::command('payments:expire-unpaid')->hourly();
Schedule::command('payments:audit-statuses')->dailyAt('02:30');
