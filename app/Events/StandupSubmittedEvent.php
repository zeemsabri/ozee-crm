<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StandupSubmittedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $standUp;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($standUp)
    {
        $this->standUp = $standUp;
    }
}
