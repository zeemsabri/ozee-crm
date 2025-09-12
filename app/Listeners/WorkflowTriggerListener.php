<?php

namespace App\Listeners;

use App\Events\WorkflowTriggerEvent;
use App\Jobs\RunWorkflowJob;
use App\Models\Workflow;

class WorkflowTriggerListener
{
    /**
     * Handle the event.
     */
    public function handle(WorkflowTriggerEvent $event): void
    {
        // Find all active workflows that match this trigger event name
        $workflows = Workflow::query()
            ->where('is_active', true)
            ->where('trigger_event', $event->eventName)
            ->get();

        foreach ($workflows as $workflow) {
            // Dispatch a queued job to run the workflow with the provided context
            RunWorkflowJob::dispatch($workflow->id, $event->context, null);
        }
    }
}
