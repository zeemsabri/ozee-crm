<?php

namespace App\Listeners;

use App\Events\WorkflowTriggerEvent;
use App\Jobs\RunWorkflowJob;
use App\Models\Workflow;
use App\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Log;

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

        $runSync = (bool) config('automation.run_synchronously', true);

        foreach ($workflows as $workflow) {
            if ($runSync) {
                // Execute immediately without needing a queue worker
                try {
                    Log::info('WorkflowTriggerListener.sync_execute', [
                        'workflow_id' => $workflow->id,
                        'event' => $event->eventName,
                    ]);
                    app(WorkflowEngineService::class)->execute($workflow->load('steps'), $event->context);
                } catch (\Throwable $e) {
                    Log::error('WorkflowTriggerListener.sync_execute_error', [
                        'workflow_id' => $workflow->id,
                        'event' => $event->eventName,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                // Dispatch a queued job to run the workflow with the provided context
                RunWorkflowJob::dispatch($workflow->id, $event->context, null);
            }
        }
    }
}
