<?php

namespace App\Actions\Points;

use App\Models\Milestone;
use App\Models\PointsLedger;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AwardMilestonePointsAction
{
    // Constants for points
    const ON_TIME_POINTS = 500;
    const LATE_POINTS = 100;

    /**
     * @var LedgerService
     */
    protected $ledgerService;

    /**
     * AwardMilestonePointsAction constructor.
     *
     * @param LedgerService $ledgerService
     */
    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Executes the business logic for awarding points for a completed milestone.
     *
     * @param Milestone $milestone The Milestone model object.
     * @return void
     */
    public function execute(Milestone $milestone): void
    {
        // Defensive Checks: Ensure all necessary data is present.
        // The user relationship on the milestone model will be null here, so we must not check for it.
        if (is_null($milestone->due_date) || is_null($milestone->submitted_at)) {
            Log::warning("AwardMilestonePointsAction called for milestone with ID {$milestone->id} but is missing a due date, or submitted timestamp. Points not awarded.");
            return;
        }

        // 1. Deduplication Check: Check if points have already been awarded for this specific Milestone.
        // We will check for any ledger entry linked to this milestone.
        $existingEntry = PointsLedger::where('pointable_id', $milestone->id)
            ->where('pointable_type', Milestone::class)
            ->first();

        if ($existingEntry) {
            // We can log this but we don't need to return anything here.
            Log::info("Points already awarded for milestone with ID {$milestone->id}. Points not re-awarded.");
            return;
        }

        // 2. On-Time vs. Late Calculation:
        // Use the project's timezone for comparison.
        // We will make an assumption here that the project has a timezone set. If not, this will default to UTC.
        $projectTimezone = $milestone->project->timezone ?? 'UTC';
        $dueDate = Carbon::parse($milestone->due_date, $projectTimezone)->endOfDay();
        $submittedAt = Carbon::parse($milestone->submitted_at)->setTimezone($projectTimezone);

        // Determine points and description based on completion time.
        $pointsToAward = 0;
        $description = '';

        if ($submittedAt->lte($dueDate)) {
            $pointsToAward = self::ON_TIME_POINTS;
            $description = 'On-Time Milestone Completion (Approved): ' . $milestone->title;
        } else {
            $pointsToAward = self::LATE_POINTS;
            $description = 'Late Milestone Completion (Approved): ' . $milestone->title;
        }

        // Get all unique users who worked on this milestone's tasks
        $taskUsers = $milestone->tasks()->pluck('assigned_to_user_id')->unique();

        // 3. Record the Transaction for each user
        foreach ($taskUsers as $userId) {
            // We need to fetch the user model to pass it to the ledger service.
            $user = \App\Models\User::find($userId);
            if ($user) {
                $this->ledgerService->record(
                    $user,
                    $pointsToAward,
                    $description,
                    'paid',
                    $milestone,
                    $milestone->project
                );
            }
        }
    }
}
