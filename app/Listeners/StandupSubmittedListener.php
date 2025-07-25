<?php

namespace App\Listeners;

use App\Events\StandupSubmittedEvent;
use App\Services\BonusProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class StandupSubmittedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The bonus processor instance.
     *
     * @var \App\Services\BonusProcessor
     */
    protected $bonusProcessor;

    /**
     * Create the event listener.
     *
     * @param \App\Services\BonusProcessor $bonusProcessor
     * @return void
     */
    public function __construct(BonusProcessor $bonusProcessor)
    {
        $this->bonusProcessor = $bonusProcessor;
    }

    /**
     * Handle the event.
     *
     * @param \App\Events\StandupSubmittedEvent $event
     * @return void
     */
    public function handle(StandupSubmittedEvent $event)
    {
        try {
            Log::info("Processing standup submission for bonus/penalty", [
                'user_id' => $event->userId,
                'project_id' => $event->projectId,
                'standup_id' => $event->standupId,
                'submission_date' => $event->submissionDate->format('Y-m-d H:i:s')
            ]);

            // Process the standup submission for potential bonus/penalty
            $transaction = $this->bonusProcessor->processStandupSubmission(
                $event->userId,
                $event->projectId,
                $event->standupId,
                $event->submissionDate
            );

            if ($transaction) {
                Log::info("Bonus/penalty transaction created", [
                    'transaction_id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'user_id' => $transaction->user_id
                ]);
            } else {
                Log::info("No bonus/penalty transaction created for standup submission");
            }
        } catch (\Exception $e) {
            Log::error("Error processing standup submission for bonus/penalty: " . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $event->userId,
                'project_id' => $event->projectId,
                'standup_id' => $event->standupId
            ]);
        }
    }
}
