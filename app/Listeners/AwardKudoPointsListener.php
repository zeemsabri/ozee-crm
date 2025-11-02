<?php

namespace App\Listeners;

use App\Events\KudoApprovedEvent;
use App\Services\PointsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AwardKudoPointsListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The service to award points.
     *
     * @var PointsService
     */
    protected $pointsService;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(PointsService $pointsService)
    {
        $this->pointsService = $pointsService;
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(KudoApprovedEvent $event)
    {
        // Check if the kudo is approved to prevent accidental point awards.
        $this->pointsService->awardPointsFor($event->kudo);
    }
}
