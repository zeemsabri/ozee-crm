<?php

namespace App\Listeners;

use App\Events\StandupSubmittedEvent;
use App\Models\ProjectNote;
use App\Services\BonusProcessor;
use App\Services\PointsService;
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
    protected $pointsService;

    public function __construct(PointsService $pointsService)
    {
        $this->pointsService = $pointsService;
    }

    public function handle(StandupSubmittedEvent $event)
    {
        Log::info("Standup submitted: " . $event->standUp->id . ' ' .  $event->standUp->type);
        if($event->standUp?->type === ProjectNote::STANDUP) {
            $this->pointsService->awardStandupPoints($event->standUp);
        }
    }
}
