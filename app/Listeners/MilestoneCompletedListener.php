<?php

namespace App\Listeners;

use App\Events\MilestoneCompletedEvent;
use App\Services\BonusProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class MilestoneCompletedListener implements ShouldQueue
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
     * @param \App\Events\MilestoneCompletedEvent $event
     * @return void
     */
    public function handle(MilestoneCompletedEvent $event)
    {
        try {
            Log::info("Processing milestone completion for bonus/penalty", [
                'user_id' => $event->userId,
                'project_id' => $event->projectId,
                'milestone_id' => $event->milestoneId,
                'completion_date' => $event->completionDate->format('Y-m-d H:i:s'),
                'due_date' => $event->dueDate->format('Y-m-d H:i:s')
            ]);

            // Process the milestone completion for potential bonus/penalty
            $transaction = $this->bonusProcessor->processMilestoneCompletion(
                $event->userId,
                $event->projectId,
                $event->milestoneId,
                $event->completionDate,
                $event->dueDate
            );

            if ($transaction) {
                Log::info("Bonus/penalty transaction created for milestone completion", [
                    'transaction_id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'user_id' => $transaction->user_id
                ]);
            } else {
                Log::info("No bonus/penalty transaction created for milestone completion");
            }
        } catch (\Exception $e) {
            Log::error("Error processing milestone completion for bonus/penalty: " . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $event->userId,
                'project_id' => $event->projectId,
                'milestone_id' => $event->milestoneId
            ]);
        }
    }
}
