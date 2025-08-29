<?php

namespace App\Providers;

use App\Events\KudoApprovedEvent;
use App\Events\MilestoneApprovedEvent;
use App\Listeners\AwardKudoPointsListener;
use App\Listeners\AwardMilestonePointsListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// Bonus system events
use App\Events\StandupSubmittedEvent;
use App\Events\TaskCompletedEvent;
use App\Events\MilestoneCompletedEvent;

// Bonus system listeners
use App\Listeners\StandupSubmittedListener;
use App\Listeners\TaskCompletedListener;
use App\Listeners\MilestoneCompletedListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Bonus system event mappings
        StandupSubmittedEvent::class => [
            StandupSubmittedListener::class,
        ],

        KudoApprovedEvent::class => [
            AwardKudoPointsListener::class
        ],

        TaskCompletedEvent::class => [
            TaskCompletedListener::class,
        ],

        MilestoneCompletedEvent::class => [
            MilestoneCompletedListener::class,
        ],

        MilestoneApprovedEvent::class   => [
            AwardMilestonePointsListener::class
        ]

    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
