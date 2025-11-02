<?php

namespace App\Events;

use App\Models\Milestone;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MilestoneApprovedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Milestone $milestone;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Milestone $milestone)
    {
        $this->milestone = $milestone;
    }
}
