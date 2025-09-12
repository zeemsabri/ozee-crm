<?php

namespace App\Contracts;

use App\Models\Schedule;

interface SchedulableAction
{
    /**
     * Execute the scheduled action.
     *
     * @param Schedule $schedule The schedule that triggered this execution.
     */
    public function runScheduled(Schedule $schedule): void;
}
