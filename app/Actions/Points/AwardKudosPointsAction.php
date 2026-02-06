<?php

namespace App\Actions\Points;

use App\Models\Kudo;
use App\Models\PointsLedger;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Log;

class AwardKudosPointsAction
{
    // Constants for points
    const KUDOS_POINTS = 25;

    /**
     * @var LedgerService
     */
    protected $ledgerService;

    /**
     * AwardKudosPointsAction constructor.
     */
    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Executes the business logic for awarding points for a received kudo.
     *
     * @param  Kudo  $kudo  The Kudo model object.
     * @return PointsLedger|null The newly created PointsLedger model instance, or null if points were not awarded.
     */
    public function execute(Kudo $kudo): ?PointsLedger
    {
        // 1. Defensive Checks: Ensure the kudo is in a valid state.
        if (! $kudo->is_approved) {
            return null;
        }

        if (is_null($kudo->recipient) || is_null($kudo->project)) {
            Log::warning("AwardKudosPointsAction called for kudo ID {$kudo->id} but it's missing a recipient or project. Points not awarded.");

            return null;
        }

        // 2. Deduplication Check: Check if points have already been awarded for this specific kudo.
        $existingEntry = PointsLedger::where('pointable_id', $kudo->id)
            ->where('pointable_type', Kudo::class)
            ->first();

        if ($existingEntry) {
            return $this->ledgerService->record(
                $kudo->recipient,
                0,
                'Denied: Points already awarded for this kudo.',
                'denied',
                $kudo,
                $kudo->project
            );
        }

        // 3. Determine Points and Description:
        $pointsToAward = self::KUDOS_POINTS;
        $description = 'Peer Kudos Received: '.$kudo->comment;

        // 4. Record the Transaction.
        return $this->ledgerService->record(
            $kudo->recipient,
            $pointsToAward,
            $description,
            'paid',
            $kudo,
            $kudo->project
        );
    }
}
