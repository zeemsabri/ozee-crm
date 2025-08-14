<?php

namespace App\Listeners;

use App\Events\MilestoneApprovedEvent;
use App\Models\Milestone;
use App\Services\PointsService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AwardMilestonePointsListener implements ShouldQueue
{
    use InteractsWithQueue;

    protected PointsService $pointsService;

    public function __construct(PointsService $pointsService)
    {
        $this->pointsService = $pointsService;
    }

    public function handle(MilestoneApprovedEvent $event)
    {
        $milestone = $event->milestone;

        if ($milestone->status === Milestone::APPROVED && $milestone->project->project_tier_id !== null) {

            $completionDate = Carbon::parse($milestone->completed_at);
            $dueDate = Carbon::parse($milestone->completion_date);
//            Log::info('Milestone completion date: ' . $completionDate);
//            Log::info('Milestone due date: ' . $dueDate);
            // Check if milestone was completed on or before the due date
            $isCompletedOnTime = $completionDate->lte($dueDate->endOfDay());
            Log::info('Milestone completed on time: ' . $isCompletedOnTime);

            // Get all unique users who worked on this milestone's tasks
            $taskUsers = $milestone->tasks()->pluck('assigned_to_user_id')->unique();

            foreach ($taskUsers as $userId) {
                // Award points to each user
                if ($isCompletedOnTime) {
                    $this->pointsService->awardMilestoneOnTimePoints($userId, $milestone);
                } else {
                    $this->pointsService->awardMilestoneLatePoints($userId, $milestone);
                }
            }
        }
    }
}
