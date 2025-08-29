<?php

namespace App\Listeners;

use App\Events\StandupSubmittedEvent;
use App\Models\ProjectNote;
use App\Services\PointsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class StandupSubmittedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     * @return void
     */
    protected $pointsService;

    public function __construct(PointsService $pointsService)
    {
        $this->pointsService = $pointsService;
    }

    public function handle(StandupSubmittedEvent $event)
    {
        if($event->standUp?->type === ProjectNote::STANDUP) {
            $this->pointsService->awardPointsFor($event->standUp);
        }
    }
}
