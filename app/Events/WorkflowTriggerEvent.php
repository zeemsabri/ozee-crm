<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowTriggerEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $eventName;
    public array $context;
    public ?string $triggeringObjectId;

    public function __construct(string $eventName, array $context = [], ?string $triggeringObjectId = null)
    {
        $this->eventName = $eventName;
        $this->context = $context;
        $this->triggeringObjectId = $triggeringObjectId;
    }
}
