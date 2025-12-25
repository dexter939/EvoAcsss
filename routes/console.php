<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('metrics:collect')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('system:check-updates --auto-stage')
    ->weekly()
    ->mondays()
    ->at('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// STOMP broker metrics polling (every minute)
Schedule::command('stomp:poll-broker')
    ->everyMinute()
    ->withoutOverlapping();

// STOMP metrics retention cleanup (daily at 2:00 AM)
Schedule::command('stomp:prune-metrics')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Tenant secret rotation (weekly on Sundays at 4:00 AM)
Schedule::command('tenant:rotate-secrets')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->withoutOverlapping()
    ->onOneServer();
