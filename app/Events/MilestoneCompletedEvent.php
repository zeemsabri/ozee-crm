<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MilestoneCompletedEvent
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
     * The milestone ID.
     *
     * @var string
     */
    public $milestoneId;

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
     * @param string $milestoneId
     * @param \DateTime $completionDate
     * @param \DateTime $dueDate
     * @return void
     */
    public function __construct($userId, $projectId, $milestoneId, $completionDate, $dueDate)
    {
        $this->userId = $userId;
        $this->projectId = $projectId;
        $this->milestoneId = $milestoneId;
        $this->completionDate = $completionDate;
        $this->dueDate = $dueDate;
    }
}
