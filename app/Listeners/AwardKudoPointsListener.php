<?php

namespace App\Listeners;

use App\Events\KudoApprovedEvent;
use App\Services\PointsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AwardKudoPointsListener
{

    /**
     * The service to award points.
     *
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
     * @param KudoApprovedEvent $event
     * @return void
     */
    public function handle(KudoApprovedEvent $event)
    {
        // Check if the kudo is approved to prevent accidental point awards.
        Log::info('triggering job');
        if ($event->kudo->is_approved) {
            Log::info('kudo award sending to service');
            $this->pointsService->awardKudosPoints($event->kudo);
        } else {
            Log::info('Kudo approved event received, but kudo status is not approved.', ['kudo_id' => $event->kudo->id]);
        }
    }
}
