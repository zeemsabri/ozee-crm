<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StandupSubmittedEvent
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
     * The standup ID.
     *
     * @var string
     */
    public $standupId;

    /**
     * The submission date.
     *
     * @var \DateTime
     */
    public $submissionDate;

    /**
     * Create a new event instance.
     *
     * @param int $userId
     * @param int $projectId
     * @param string $standupId
     * @param \DateTime $submissionDate
     * @return void
     */
    public function __construct($userId, $projectId, $standupId, $submissionDate)
    {
        $this->userId = $userId;
        $this->projectId = $projectId;
        $this->standupId = $standupId;
        $this->submissionDate = $submissionDate;
    }
}
