<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command(\App\Console\Commands\FetchEmails::class)->everyFiveMinutes();

Schedule::job(new \App\Jobs\FetchCurrencyRatesJob)->daily();
