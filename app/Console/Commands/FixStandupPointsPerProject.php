<?php

namespace App\Console\Commands;

use App\Models\MonthlyPoint;
use App\Models\PointsLedger;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixStandupPointsPerProject extends Command
{
    protected $signature = 'points:fix-standups {--from=} {--dry-run}';

    protected $description = 'Backfill missing standup points per project (once-per-day-per-project) and update MonthlyPoint totals starting from the first PointsLedger entry.';

    private const BASE_POINTS_STANDUP_ON_TIME = 25;
    private const BASE_POINTS_STANDUP_LATE = 10;

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        $firstLedgerAt = PointsLedger::min('created_at');
        if (!$firstLedgerAt) {
            $this->info('No PointsLedger entries found. Nothing to fix.');
            return self::SUCCESS;
        }

        $fromInput = $this->option('from');
        $startDate = $fromInput ? Carbon::parse($fromInput)->startOfDay() : Carbon::parse($firstLedgerAt)->startOfDay();
        $endDate = Carbon::now();

        $this->info("Fixing standup points from {$startDate->toDateString()} to {$endDate->toDateString()}" . ($dryRun ? ' (dry run)' : ''));

        $totalChecked = 0;
        $totalCreated = 0;

        // We only consider ProjectNote standups created on/after startDate
        ProjectNote::where('type', ProjectNote::STANDUP)
            ->where('creator_type', User::class)
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->orderBy('created_at')
            ->chunkById(1000, function ($notes) use (&$totalChecked, &$totalCreated, $dryRun) {
                foreach ($notes as $note) {
                    /** @var ProjectNote $note */
                    $totalChecked++;

                    $user = User::find($note->creator_id);
                    if (!$user) {
                        $this->warn("Skipping note {$note->id}: user {$note->creator_id} not found");
                        continue;
                    }

                    // Ensure we are enforcing per-project per-day: create a ledger for this specific standup note if missing
                    $exists = PointsLedger::where('user_id', $note->creator_id)
                        ->where('pointable_type', ProjectNote::class)
                        ->where('pointable_id', $note->id)
                        ->exists();

                    if ($exists) {
                        continue; // already has a ledger tied to this standup
                    }

                    // Compute points (on-time vs late) using user's timezone and 11:00 deadline
                    $userTz = $user->timezone ?? config('app.timezone');
                    $standupAtUserTz = Carbon::parse($note->created_at)->setTimezone($userTz);
                    $deadline = (clone $standupAtUserTz)->startOfDay()->setTime(11, 0, 0);
                    $isLate = $standupAtUserTz->gt($deadline);
                    $basePoints = $isLate ? self::BASE_POINTS_STANDUP_LATE : self::BASE_POINTS_STANDUP_ON_TIME;
                    $description = $isLate ? 'Late Daily Standup' : 'On-Time Daily Standup';

                    // Apply project tier multiplier if available
                    $finalPoints = $basePoints;
                    $project = Project::find($note->project_id);
                    if ($project) {
                        // Prefer Project::tier() relation; fallback gracefully
                        $multiplier = optional($project->tier)->point_multiplier ?? 1.0;
                        $finalPoints = $basePoints * (float)$multiplier;
                    }

                    if ($dryRun) {
                        $this->line("Would create ledger for standup #{$note->id} (user {$note->creator_id}, project {$note->project_id}) = {$finalPoints} pts, desc='{$description}', date={$standupAtUserTz->toDateString()}");
                        $totalCreated++;
                        continue;
                    }

                    DB::beginTransaction();
                    try {
                        $ledger = new PointsLedger([
                            'user_id' => $note->creator_id,
                            'project_id' => $note->project_id,
                            'points_awarded' => $finalPoints,
                            'description' => $description,
                            'pointable_id' => $note->id,
                            'pointable_type' => ProjectNote::class,
                            'status' => PointsLedger::STATUS_PAID,
                            'meta' => ['standup_date' => Carbon::parse($note->created_at)->toDateString()],
                        ]);
                        // Set timestamps to match the standup creation time so MonthlyPoint periods align
                        $ledger->created_at = $note->created_at;
                        $ledger->updated_at = $note->created_at;
                        $ledger->save();

                        // Update monthly points for the correct month/year of the standup
                        $standupDate = Carbon::parse($note->created_at);
                        /** @var MonthlyPoint $monthly */
                        $monthly = MonthlyPoint::firstOrCreate(
                            ['user_id' => $note->creator_id, 'year' => (int)$standupDate->year, 'month' => (int)$standupDate->month],
                            ['total_points' => 0]
                        );
                        $monthly->increment('total_points', $finalPoints);

                        DB::commit();
                        $totalCreated++;
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        $this->error('Failed to create ledger for standup ' . $note->id . ': ' . $e->getMessage());
                    }
                }
            });

        $this->info("Checked standups: {$totalChecked}. Created missing ledgers: {$totalCreated}.");
        $this->info('Done.');

        return self::SUCCESS;
    }
}
