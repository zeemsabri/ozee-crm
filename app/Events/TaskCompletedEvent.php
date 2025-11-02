<?php

namespace App\Events;

use App\Models\Milestone;
use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCompletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Task $task;

    public Milestone $milestone;

    /**
     * Create a new event instance.
     *
     * @param  Task  $task
     * @param  Milestone  $milestone
     * @return void
     */
    public function __construct($task, $milestone)
    {
        $this->task = $task;
        $this->milestone = $milestone;
    }
}
