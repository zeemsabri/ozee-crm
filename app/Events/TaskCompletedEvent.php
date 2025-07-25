<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCompletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The user ID.
     *
     * @var int
     */
    public $userId;

    /**
     * The project ID.
     *
     * @var int
     */
    public $projectId;

    /**
     * The task ID.
     *
     * @var string
     */
    public $taskId;

    /**
     * The completion date.
     *
     * @var \DateTime
     */
    public $completionDate;

    /**
     * The due date.
     *
     * @var \DateTime
     */
    public $dueDate;

    /**
     * Create a new event instance.
     *
     * @param int $userId
     * @param int $projectId
     * @param string $taskId
     * @param \DateTime $completionDate
     * @param \DateTime $dueDate
     * @return void
     */
    public function __construct($userId, $projectId, $taskId, $completionDate, $dueDate)
    {
        $this->userId = $userId;
        $this->projectId = $projectId;
        $this->taskId = $taskId;
        $this->completionDate = $completionDate;
        $this->dueDate = $dueDate;
    }
}
