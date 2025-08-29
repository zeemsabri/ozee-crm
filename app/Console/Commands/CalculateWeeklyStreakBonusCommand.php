<?php

namespace App\Console\Commands;

use App\Models\PointsLedger;
use App\Models\ProjectNote;
use App\Models\User;
use App\Models\WeeklyStreak;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateWeeklyStreakBonusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:calculate-streak';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates and awards the weekly standup streak bonus.';



    /**
     * @var LedgerService
     */
    protected $ledgerService;

    /**
     * CalculateWeeklyStreakBonusCommand constructor.
     *
     * @param LedgerService $ledgerService
     */
    public function __construct(LedgerService $ledgerService)
    {
        parent::__construct();
        $this->ledgerService = $ledgerService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Get all users who are eligible for points.
        $eligibleUsers = User::all();

        $this->info("Calculating weekly standup streaks for " . count($eligibleUsers) . " users...");

        foreach ($eligibleUsers as $user) {
            $userTimezone = $user->timezone ?? 'Asia/Karachi';
            $startOfWeek = Carbon::now($userTimezone)->startOfWeek()->setTimezone('UTC');
            $endOfWeek = Carbon::now($userTimezone)->endOfWeek(Carbon::FRIDAY)->setTimezone('UTC');

            // Deduplication Check: Check if a streak bonus has already been awarded this week.
            $existingBonus = PointsLedger::where('user_id', $user->id)
                ->where('description', 'Weekly Standup Streak Bonus')
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->first();

            if ($existingBonus) {
                $this->info("User ID {$user->id} already received a streak bonus this week. Skipping.");
                continue;
            }

            // Fetch all standups for the week and check them in PHP.
            $standups = ProjectNote::query()
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->get();

            $onTimeStandupCount = 0;
            foreach ($standups as $standup) {
                if ($standup->isBeforeUserTime('11:00:00')) {
                    $onTimeStandupCount++;
                }
            }

            // Check if the user met the streak requirement.
            if ($onTimeStandupCount >= self::REQUIRED_STANDUPS) {
                $this->ledgerService->record(
                    $user,
                    WeeklyStreak::WEEKLY_STREAK_BONUS,
                    'Weekly Standup Streak Bonus',
                    'paid',
                    new WeeklyStreak(['id' => 1]),
                    null
                );
                $this->info("Awarded weekly streak bonus to user ID {$user->id}.");
            } else {
                $this->info("User ID {$user->id} did not meet the streak requirement. Found {$onTimeStandupCount} on-time standups.");
            }
        }

        $this->info("Weekly streak bonus calculation complete.");
    }
}
