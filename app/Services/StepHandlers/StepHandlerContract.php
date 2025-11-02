<?php

namespace App\Services\StepHandlers;

use App\Models\ExecutionLog;
use App\Models\WorkflowStep;

interface StepHandlerContract
{
    /**
     * Handle a workflow step and return an array with optional keys:
     * - context: array Additional context to merge into global context
     * - output: mixed Raw output
     * - logs: array Arbitrary info for debugging
     */
    public function handle(array $context, WorkflowStep $step, ?ExecutionLog $execLog = null): array;
}
