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
     * @return PointsLedger|null The newly created PointsLedger model instance, or null if points were not awarded.
     */
    public function execute(Milestone $milestone): ?PointsLedger
    {
        // Defensive Checks: Ensure all necessary data is present.
        if (is_null($milestone->user) || is_null($milestone->due_date) || is_null($milestone->submitted_at)) {
            Log::warning("AwardMilestonePointsAction called for milestone with ID {$milestone->id} but is missing a user, due date, or submitted timestamp. Points not awarded.");
            return null;
        }

        // 1. Deduplication Check: Check if points have already been awarded for this specific Milestone.
        $existingEntry = PointsLedger::where('pointable_id', $milestone->id)
            ->where('pointable_type', Milestone::class)
            ->first();

        if ($existingEntry) {
            return $this->ledgerService->record(
                $milestone->user,
                0,
                'Denied: Points already awarded for this milestone.',
                'denied',
                $milestone,
                $milestone->project
            );
        }

        // 2. On-Time vs. Late Calculation:
        // Use the user's timezone for comparison.
        $userTimezone = $milestone->user->timezone;
        $dueDate = Carbon::parse($milestone->due_date, $userTimezone)->endOfDay();
        $submittedAt = Carbon::parse($milestone->submitted_at)->setTimezone($userTimezone);

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

        // 3. Record the Transaction.
        return $this->ledgerService->record(
            $milestone->user,
            $pointsToAward,
            $description,
            'paid',
            $milestone,
            $milestone->project
        );
    }
}
