<?php

namespace App\Console;

use App\Console\Commands\FetchEmails;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\FetchEmails::class,
        \App\Console\Commands\CheckMissedBonusesCommand::class,
        \App\Console\Commands\FixStandupPointsPerProject::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Log that the schedule method is being called
        \Illuminate\Support\Facades\Log::info('Schedule method called in Console/Kernel.php');

        // Run the emails:fetch command every 5 minutes using cron expression
//        $schedule->command('emails:fetch')->cron('*/5 * * * *');

//        $schedule->command(FetchEmails::class);

        // Run the check-missed-bonuses command daily at 1:00 AM
        $schedule->command('app:check-missed-bonuses')
            ->dailyAt('01:00')
            ->appendOutputTo(storage_path('logs/check-missed-bonuses.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
