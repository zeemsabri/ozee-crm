<?php

namespace App\Actions\Points;

use App\Models\Email;
use App\Models\PointsLedger;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AwardEmailPointsAction
{
    // Constants for points
    const EMAIL_POINTS = 50;

    /**
     * @var LedgerService
     */
    protected $ledgerService;

    /**
     * AwardEmailPointsAction constructor.
     *
     * @param LedgerService $ledgerService
     */
    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Executes the business logic for awarding points for a sent email.
     *
     * @param Email $email The Email model object.
     * @return PointsLedger|null The newly created PointsLedger model instance, or null if points were not awarded.
     */
    public function execute(Email $email): ?PointsLedger
    {
        // 1. Defensive Checks:
        if ($email->type !== 'sent' || $email->status !== 'sent') {
            Log::info("Email points not awarded for email ID {$email->id} because it is not a sent email.");
            return null;
        }

        $project = $email->project();

        if (is_null($email->sender) || is_null($project)) {
            Log::warning("AwardEmailPointsAction called for email ID {$email->id} but it's missing a sender or project. Points not awarded.");
            return null;
        }

        // 2. Deduplication Check:
        $existingEntry = PointsLedger::where('pointable_id', $email->id)
            ->where('pointable_type', Email::class)
            ->first();

        if ($existingEntry) {
            return $this->ledgerService->record(
                $email->sender,
                0,
                'Denied: Points already awarded for this email.',
                'denied',
                $email,
                $project,
                $existingEntry->created_at
            );
        }

        // 3. Timeliness Calculation:
        $sentAt = Carbon::parse($email->created_at);
        $windowStart = null;

        if ($project->last_email_received && $sentAt->gt(Carbon::parse($project->last_email_received))) {
            // The cached value is valid and more recent, so we can use it.
            $windowStart = Carbon::parse($project->last_email_received);
        } else {
            // The cached value is either null or a stale timestamp from a later email.
            // We need to find the true preceding received email.
            $precedingEmail = Email::whereHas('conversation', function ($q) use ($project) {
                    $q->where('project_id', $project->id);
                })
                ->where('type', 'received')
                ->where('created_at', '<', $sentAt)
                ->orderBy('sent_at', 'desc')
                ->first();

            if ($precedingEmail) {
                $windowStart = Carbon::parse($precedingEmail->sent_at);
            }
        }

        $isTimely = false;
        if ($windowStart) {
            $windowEnd = $windowStart->copy()->addHours(4);
            $isTimely = $sentAt->between($windowStart, $windowEnd);
        }

        if ($isTimely) {
            $pointsToAward = self::EMAIL_POINTS;
            $description = 'Timely Email Reply';
            $status = 'paid';
        } else {
            $pointsToAward = 0;
            $description = 'Denied: Email reply not within the 4-hour window. Last Email was received on ' . $windowStart?->toDateTimeString() . ' and this email was sent on ' . $email->created_at ;
            $status = 'denied';
        }


        // 4. Record the Transaction:
        return $this->ledgerService->record(
            $email->sender,
            $pointsToAward,
            $description,
            $status,
            $email,
            $project,
            $email->created_at
        );
    }
}
