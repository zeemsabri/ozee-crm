<?php

namespace App\Events;

use App\Models\Kudo;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KudoApprovedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The Kudo instance.
     *
     * @var Kudo
     */
    public $kudo;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Kudo $kudo)
    {
        $this->kudo = $kudo;
    }
}
