<?php

namespace App\Jobs;

use App\Models\Workflow;
use App\Services\WorkflowEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $workflowId,
        public array $context = [],
        public ?int $startStepId = null,
    ) {}

    public function handle(WorkflowEngineService $engine): void
    {
        $workflow = Workflow::with('steps')->find($this->workflowId);
        if (!$workflow) {
            return; // swallowed; could log
        }

        if ($this->startStepId) {
            $engine->executeFromStepId($workflow, $this->context, $this->startStepId);
        } else {
            $engine->execute($workflow, $this->context);
        }
    }
}
