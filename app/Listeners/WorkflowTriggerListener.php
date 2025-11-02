<?php

namespace App\Listeners;

use App\Events\WorkflowTriggerEvent;
use App\Jobs\RunWorkflowJob;
use App\Models\Workflow;
use Illuminate\Support\Facades\Log;

class WorkflowTriggerListener
{
    /**
     * Handle the event.
     */
    public function handle(WorkflowTriggerEvent $event): void
    {
        // Find all active workflows that match this trigger event name
        $workflows = Workflow::where('is_active', 1)
            ->where('trigger_event', $event->eventName)
            ->get();

        Log::info('WorkflowTriggerListener.handle', [
            'count' => 'running for '.$workflows->count(),
            'from' => $event->from,
        ]);

        foreach ($workflows as $workflow) {
            // Enrich context with event metadata for downstream use and unique key derivation
            $ctx = $event->context;
            $ctx['event'] = $event->eventName;
            if (! isset($ctx['trigger'])) {
                $ctx['trigger'] = $ctx;
            }
            $ctx['triggering_object_id'] = $event->triggeringObjectId;

            $uniqueKey = 'workflow:'.$workflow->id.'|event:'.$event->eventName.'|object:'.($event->triggeringObjectId ?? '');

            Log::info('WorkflowTriggerListener.dispatch_job', [
                'workflow_id' => $workflow->id,
                'event' => $event->eventName,
                'unique' => $uniqueKey,
            ]);

            RunWorkflowJob::dispatch($workflow->id, $ctx, null, $uniqueKey);
        }
    }
}
