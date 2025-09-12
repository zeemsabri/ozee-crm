<?php

namespace App\Providers;

use App\Events\KudoApprovedEvent;
use App\Events\MilestoneApprovedEvent;
use App\Events\WorkflowTriggerEvent;
use App\Listeners\AwardKudoPointsListener;
use App\Listeners\AwardMilestonePointsListener;
use App\Listeners\WorkflowTriggerListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// Bonus system events
use App\Events\StandupSubmittedEvent;
use App\Events\TaskCompletedEvent;

// Bonus system listeners
use App\Listeners\StandupSubmittedListener;
use App\Listeners\TaskCompletedListener;

// Automation global model subscriber
use App\Listeners\GlobalModelEventSubscriber;

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

        MilestoneApprovedEvent::class   => [
            AwardMilestonePointsListener::class
        ],

        // Automation engine trigger mapping
        WorkflowTriggerEvent::class => [
            WorkflowTriggerListener::class,
        ],

    ];

    /**
     * Register event subscribers.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        GlobalModelEventSubscriber::class,
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
