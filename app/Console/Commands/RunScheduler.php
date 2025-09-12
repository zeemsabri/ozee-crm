<?php

namespace App\Console\Commands;

use App\Jobs\RunScheduledItem;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunScheduler extends Command
{
    protected $signature = 'app:run-scheduler {--now=} {--limit=500}';

    protected $description = 'Scan active schedules and dispatch due jobs. Use as the single cron entry (every minute).';

    public function handle(): int
    {
        $nowOption = $this->option('now');
        $now = $nowOption ? Carbon::parse($nowOption) : now();
        $startOfMinute = $now->copy()->startOfMinute();

        $this->info('Running scheduler at ' . $now->toDateTimeString());

        $limit = (int) $this->option('limit');
        $count = 0;
        $dispatched = 0;

        Schedule::query()
            ->active()
            ->withinWindow($now)
            ->orderBy('id')
            ->chunkById(200, function ($schedules) use (&$count, &$dispatched, $now, $startOfMinute) {
                /** @var Schedule $schedule */
                foreach ($schedules as $schedule) {
                    $count++;
                    // Prevent running more than once within the same minute
                    if ($schedule->last_run_at && $schedule->last_run_at->greaterThanOrEqualTo($startOfMinute)) {
                        continue;
                    }

                    if (!$schedule->isDueAt($now)) {
                        Log::info('not due yet');
                        continue;
                    }

                    // Simple distributed lock per minute
                    $lockKey = 'schedule:' . $schedule->id . ':' . $startOfMinute->format('YmdHi');
                    $lock = Cache::lock($lockKey, 55);
                    if (!$lock->get()) {
                        continue; // someone else is dispatching it
                    }

                    try {
                        RunScheduledItem::dispatch($schedule->id);
                        $dispatched++;
                    } finally {
                        // Release lock early; the job itself will update last_run_at
                        optional($lock)->release();
                    }
                }
            }, 'id', 'id');

        $this->info("Checked $count schedules, dispatched $dispatched jobs.");
        Log::info('RunScheduler summary', compact('count', 'dispatched'));

        return self::SUCCESS;
    }
}
