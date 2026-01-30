<?php

namespace App\Console\Commands;

use App\Models\LoginAttempt;
use App\Models\MagicLink;
use Illuminate\Console\Command;

class CleanupClientAuthData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:cleanup-client-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup expired login attempts and temporary PINs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup...');

        // 1. Delete login attempts older than 24 hours
        $deletedAttempts = LoginAttempt::where('attempted_at', '<', now()->subHours(24))->delete();
        $this->line("Deleted {$deletedAttempts} old login attempts.");

        // 2. Clear expired temporary PINs (for privacy and storage efficiency)
        $clearedPins = MagicLink::where('temp_pin_expires_at', '<', now())
            ->whereNotNull('temporary_pin')
            ->update([
                'temporary_pin' => null,
                'temp_pin_expires_at' => null
            ]);
        $this->line("Cleared {$clearedPins} expired temporary PINs.");

        $this->info('Cleanup completed successfully.');
    }
}
