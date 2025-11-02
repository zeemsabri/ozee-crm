<?php

namespace App\Listeners;

use App\Events\MilestoneApprovedEvent;
use App\Services\PointsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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

        $this->pointsService->awardPointsFor($milestone);

    }
}
