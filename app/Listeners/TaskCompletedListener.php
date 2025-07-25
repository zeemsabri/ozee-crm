<?php

namespace App\Listeners;

use App\Events\TaskCompletedEvent;
use App\Services\BonusProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class TaskCompletedListener implements ShouldQueue
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
     * @param \App\Events\TaskCompletedEvent $event
     * @return void
     */
    public function handle(TaskCompletedEvent $event)
    {
        try {
            Log::info("Processing task completion for bonus/penalty", [
                'user_id' => $event->userId,
                'project_id' => $event->projectId,
                'task_id' => $event->taskId,
                'completion_date' => $event->completionDate->format('Y-m-d H:i:s'),
                'due_date' => $event->dueDate->format('Y-m-d H:i:s')
            ]);

            // Process the task completion for potential bonus/penalty
            $transaction = $this->bonusProcessor->processTaskCompletion(
                $event->userId,
                $event->projectId,
                $event->taskId,
                $event->completionDate,
                $event->dueDate
            );

            if ($transaction) {
                Log::info("Bonus/penalty transaction created for task completion", [
                    'transaction_id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'user_id' => $transaction->user_id
                ]);
            } else {
                Log::info("No bonus/penalty transaction created for task completion");
            }
        } catch (\Exception $e) {
            Log::error("Error processing task completion for bonus/penalty: " . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $event->userId,
                'project_id' => $event->projectId,
                'task_id' => $event->taskId
            ]);
        }
    }
}
