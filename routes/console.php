<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(\App\Console\Commands\FetchEmails::class)->everyMinute();
//
Schedule::job(new \App\Jobs\FetchCurrencyRatesJob)->daily();

Schedule::command('queue:work --stop-when-empty')->everyMinute();

Schedule::command('points:calculate-streak')->weeklyOn(7);
//
// Schedule::command('leads:process-new')->everyFourHours();
//
// Schedule::command('leads:process-follow-ups')->daily();
//
Schedule::command('auth:cleanup-client-data')->hourly();

Schedule::command('app:run-scheduler')->everyMinute();
