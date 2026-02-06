<?php

namespace App\Jobs;

use App\Models\Workflow;
use App\Services\WorkflowEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunWorkflowJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Seconds to keep the unique lock before processing starts.
     */
    public int $uniqueFor = 60;

    public int $tries = 3;

    public int $timeout = 120; // seconds

    public bool $failOnTimeout = true;

    public $backoff = [60, 300, 900]; // 1m, 5m, 15m

    public function __construct(
        public int $workflowId,
        public array $context = [],
        public ?int $startStepId = null,
        public ?string $uniqueKey = null,
    ) {}

    public function uniqueId(): string
    {
        if ($this->uniqueKey) {
            return $this->uniqueKey;
        }
        $event = $this->context['event'] ?? ($this->context['trigger']['event'] ?? '');
        $objectId = $this->context['triggering_object_id'] ?? ($this->context['trigger']['id'] ?? '');

        return 'workflow:'.$this->workflowId.'|event:'.$event.'|object:'.$objectId;
    }

    public function handle(WorkflowEngineService $engine): void
    {
        $workflow = Workflow::with('steps')->find($this->workflowId);
        if (! $workflow) {
            return; // swallowed; could log
        }

        if ($this->startStepId) {
            $engine->executeFromStepId($workflow, $this->context, $this->startStepId);
        } else {
            $engine->execute($workflow, $this->context);
        }
    }
}
