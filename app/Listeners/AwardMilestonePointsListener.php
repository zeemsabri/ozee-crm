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

        $this->pointsService->awardPointsFor($milestone);

    }
}
