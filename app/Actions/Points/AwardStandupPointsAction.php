<?php

namespace App\Actions\Points;

use App\Models\PointsLedger;
use App\Models\ProjectNote as Standup;
use App\Services\LedgerService;
use Carbon\Carbon;

class AwardStandupPointsAction
{
    // Constants for points
    const ON_TIME_POINTS = 25;

    const LATE_POINTS = 10;

    /**
     * @var LedgerService
     */
    protected $ledgerService;

    /**
     * AwardStandupPointsAction constructor.
     */
    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Executes the business logic for awarding points for a daily standup.
     *
     * @param  Standup  $standup  The Standup model object.
     * @return PointsLedger|void The newly created PointsLedger model instance.
     */
    public function execute(Standup $standup): PointsLedger
    {
        // 1. Deduplication Check (Timezone-Aware):
        // We must check for duplicates based on the user's local calendar day, not the server's.

        // Note: For optimal performance, the calling code should eager-load the 'user' and 'project' relationships.
        // E.g., Standup::with('user', 'project')->find(...)

        if (! $standup->user) {
            return new PointsLedger;
        }
        $userTimezone = $standup->user->timezone;
        $startOfDay = Carbon::now($userTimezone)->startOfDay()->setTimezone('UTC');
        $endOfDay = Carbon::now($userTimezone)->endOfDay()->setTimezone('UTC');

        $existingEntry = PointsLedger::where('user_id', $standup->user->id)
            ->where('pointable_type', Standup::class)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->where('project_id', $standup->project->id)
            ->first();

        if ($existingEntry) {
            // If a duplicate entry is found, record a denied transaction and exit.
            return $this->ledgerService->record(
                $standup->user,
                0,
                'Denied: Points already awarded for a daily standup today: '.$startOfDay->toDateString(),
                'denied',
                $standup,
                $standup->project,
                $standup->created_at
            );
        }

        // 2. On-Time vs. Late Calculation: Determine if the standup was submitted on time.
        // Assuming the Standup model has the HasUserTimezone trait.
        if ($standup->isBeforeUserTime('11:00:00')) {
            $pointsToAward = self::ON_TIME_POINTS;
            $description = 'On-Time Daily Standup on '.$standup->created_at->format('M j, Y');
        } else {
            $pointsToAward = self::LATE_POINTS;
            $description = 'Late Daily Standup on '.$standup->created_at->format('M j, Y');
        }

        // 3. Record the Transaction: Call the LedgerService to save the transaction.
        return $this->ledgerService->record(
            $standup->user,
            $pointsToAward,
            $description,
            'paid',
            $standup,
            $standup->project
        );
    }
}
