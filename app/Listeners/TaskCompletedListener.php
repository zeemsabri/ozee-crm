<?php

namespace App\Listeners;

use App\Events\TaskCompletedEvent;
use App\Services\PointsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class TaskCompletedListener implements ShouldQueue
{

    use InteractsWithQueue;
    /**
     * @var PointsService
     */
    protected $pointsService;

    /**
     * Create the event listener.
     *
     * @param PointsService $pointsService
     * @return void
     */
    public function __construct(PointsService $pointsService)
    {
        $this->pointsService = $pointsService;
    }

    /**
     * Handle the event.
     *
     * @param TaskCompletedEvent $event
     * @return void
     */
    public function handle(TaskCompletedEvent $event)
    {
        try {
            $this->pointsService->awardPointsFor($event->task);
        } catch (\Exception $e) {
            Log::error('Failed to award points for task completion.', [
                'task_id' => $event->task->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
