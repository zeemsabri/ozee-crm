<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(\App\Console\Commands\FetchEmails::class)->everyMinute();

/*
 * Ingest mail sent from the Gmail web UI.
 *
 * The line above fetches `is:inbox` only, so a reply typed into Gmail rather than composed
 * in the CRM never becomes a row and its thread stays "Needs reply" forever. This pass
 * closes that. Every five minutes rather than every minute: it is a correction to the
 * record, not something anyone is waiting on, and each pass costs a Gmail list plus a get
 * per message. withoutOverlapping() because a slow pass must not run two watermarks at
 * once. See App\Services\Inbox\IngestSentMail.
 */
if (config('inbox.sent_ingest.enabled')) {
    Schedule::command('inbox:fetch-sent')->everyFiveMinutes()->withoutOverlapping();
}

/*
 * Sweep files whose expires_at has passed.
 *
 * Only touches rows that explicitly opted into expiry — today that is the inbox block
 * builder's images, whose real copy travels inside the sent message as a CID part, so
 * dropping ours after the TTL loses nothing the client can see. Every pre-existing row has
 * a null expires_at and is never considered. Daily is deliberate: the TTL is measured in
 * months, so there is nothing to gain from checking more often, and the command deletes
 * GCS objects one at a time.
 */
Schedule::command('files:prune-expired')->dailyAt('03:15')->withoutOverlapping();

Schedule::job(new \App\Jobs\FetchCurrencyRatesJob)->daily();
Schedule::job(new \App\Jobs\XeroPaymentSyncJob)->daily();

Schedule::command('xero:refresh-payment-services')->everySixHours();
Schedule::command('xero:sync-invoices')->hourly();

Schedule::command('queue:work --stop-when-empty')->everyMinute();

Schedule::command('queue:work --queue=emails --stop-when-empty')->everyMinute();

Schedule::command('points:calculate-streak')->weeklyOn(7);
//
// Schedule::command('leads:process-new')->everyFourHours();
//
// Schedule::command('leads:process-follow-ups')->daily();
//
Schedule::command('auth:cleanup-client-data')->hourly();

Schedule::command('app:run-scheduler')->everyMinute();
