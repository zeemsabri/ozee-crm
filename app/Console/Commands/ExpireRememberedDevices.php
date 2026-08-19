<?php

namespace App\Console\Commands;

use App\Models\UserRememberedDevice;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Clears remembered devices.
 *
 * Run once with --all at the deploy that gives the device cookie the power to restore
 * a session. Before that change the cookie only skipped the OTP step; afterwards the
 * same cookie signs its holder in. Anyone still carrying one issued under the old,
 * weaker meaning would silently gain more than they were granted, so they are cleared
 * and everyone signs in once more.
 *
 * Without --all it only removes rows that have already expired, which is safe to run
 * on a schedule.
 */
class ExpireRememberedDevices extends Command
{
    protected $signature = 'devices:expire
                            {--all : Remove every remembered device, not just expired ones}';

    protected $description = 'Remove expired remembered devices (or all of them with --all)';

    public function handle(): int
    {
        $query = UserRememberedDevice::query();

        if ($this->option('all')) {
            $count = $query->count();

            if ($count === 0) {
                $this->info('No remembered devices to clear.');

                return self::SUCCESS;
            }

            if (! $this->confirm("Clear all {$count} remembered devices? Everyone signs in again.", true)) {
                $this->warn('Left alone.');

                return self::SUCCESS;
            }

            $query->delete();
            $this->info("Cleared {$count} remembered devices.");

            return self::SUCCESS;
        }

        $deleted = $query->where('expires_at', '<=', Carbon::now())->delete();
        $this->info("Removed {$deleted} expired remembered device(s).");

        return self::SUCCESS;
    }
}
