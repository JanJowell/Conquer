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
Schedule::command('users:prune-unverified --days=7')
    ->dailyAt('03:15')
    ->withoutOverlapping();
Schedule::command('community-posts:purge-archived --days=30')
    ->dailyAt('03:30')
    ->withoutOverlapping();
