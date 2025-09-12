<?php

namespace App\Services;

use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionLog;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\StepHandlers\StepHandlerContract;
use App\Services\StepHandlers\AiPromptStepHandler;
use App\Services\StepHandlers\ConditionStepHandler;
use App\Services\StepHandlers\CreateRecordStepHandler;
use App\Services\StepHandlers\SendEmailStepHandler;
use App\Services\StepHandlers\UpdateRecordStepHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowEngineService
{
    /** @var array<string, StepHandlerContract> */
    protected array $handlers;

    public function __construct(
        AIGenerationService $ai,
    ) {
        // Register default handlers
        $this->handlers = [
            'AI_PROMPT' => new AiPromptStepHandler($ai),
            'CONDITION' => new ConditionStepHandler($this),
            'ACTION_CREATE_RECORD' => new CreateRecordStepHandler(),
            'ACTION_UPDATE_RECORD' => new UpdateRecordStepHandler(),
            'ACTION_SEND_EMAIL' => new SendEmailStepHandler(),
            // TRIGGER steps are structural; at runtime they are a no-op
            'TRIGGER' => new class implements StepHandlerContract {
                public function handle(array $context, WorkflowStep $step): array
                {
                    return [
                        'parsed' => [
                            'trigger_event' => $step->step_config['trigger_event'] ?? null,
                        ],
                    ];
                }
            },
            // alias plain ACTION to action_type in step_config
            'ACTION' => new class implements StepHandlerContract {
                public function handle(array $context, WorkflowStep $step): array
                {
                    $type = strtoupper((string)($step->step_config['action_type'] ?? ''));
                    // No-op; actual dispatch handled in engine's resolveHandler
                    return ['logs' => ['action_type' => $type]];
                }
            },
        ];
    }

    public function registerHandler(string $type, StepHandlerContract $handler): void
    {
        $this->handlers[strtoupper($type)] = $handler;
    }

    protected function resolveHandler(WorkflowStep $step): ?StepHandlerContract
    {
        $type = strtoupper($step->step_type);
        if ($type === 'ACTION') {
            $actionType = strtoupper((string)($step->step_config['action_type'] ?? ''));
            $type = 'ACTION_' . $actionType;
        }
        return $this->handlers[$type] ?? null;
    }

    /**
     * Execute the given workflow with the provided context.
     * Returns an array summary with logs per step.
     */
    public function execute(Workflow $workflow, array $context = [], ?ExecutionLog $parentLog = null): array
    {
        $results = [
            'workflow_id' => $workflow->id,
            'steps' => [],
        ];

        $steps = $workflow->steps()->orderBy('step_order')->get();

        foreach ($steps as $step) {
            // Honor delay_minutes at the step boundary by scheduling a resume job and stopping here
            if (($step->delay_minutes ?? 0) > 0) {
                $execLog = ExecutionLog::create([
                    'workflow_id' => $workflow->id,
                    'step_id' => $step->id,
                    'parent_execution_log_id' => $parentLog?->id,
                    'status' => 'scheduled',
                    'input_context' => $context,
                ]);
                // Dispatch a delayed job to resume from this step
                RunWorkflowJob::dispatch($workflow->id, $context, $step->id)->delay(now()->addMinutes((int) $step->delay_minutes));
                $results['steps'][] = [
                    'step_id' => $step->id,
                    'status' => 'scheduled',
                    'delay_minutes' => (int) $step->delay_minutes,
                ];
                break; // stop current execution; it will resume later
            }

            $start = microtime(true);
            $logData = [
                'workflow_id' => $workflow->id,
                'step_id' => $step->id,
                'parent_execution_log_id' => $parentLog?->id,
                'status' => 'started',
                'input_context' => $context,
            ];

            $execLog = ExecutionLog::create($logData);

            try {
                $handler = $this->resolveHandler($step);
                if (!$handler) {
                    throw new \RuntimeException("No handler for step type {$step->step_type}");
                }
                $out = $handler->handle($context, $step);

                $duration = (int) ((microtime(true) - $start) * 1000);
                $execLog->update([
                    'status' => 'success',
                    'raw_output' => $out['raw'] ?? ($out['output'] ?? null),
                    'parsed_output' => $out['parsed'] ?? null,
                    'duration_ms' => $duration,
                    'token_usage' => $out['token_usage'] ?? null,
                    'cost' => $out['cost'] ?? null,
                ]);

                // Merge context if provided
                if (!empty($out['context']) && is_array($out['context'])) {
                    $context = array_replace_recursive($context, $out['context']);
                }

                $results['steps'][] = [
                    'step_id' => $step->id,
                    'status' => 'success',
                    'duration_ms' => $duration,
                ];
            } catch (Throwable $e) {
                Log::error('WorkflowEngineService.execute.step_error', [
                    'workflow_id' => $workflow->id,
                    'step_id' => $step->id,
                    'error' => $e->getMessage(),
                ]);
                $execLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $results['steps'][] = [
                    'step_id' => $step->id,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                // Decision: continue on error; alternatively we could break
            }
        }

        return $results;
    }

    /**
     * Resume execution from a specific top-level step id (used for delayed steps)
     */
    public function executeFromStepId(Workflow $workflow, array $context, int $startStepId, ?ExecutionLog $parentLog = null): array
    {
        $results = [
            'workflow_id' => $workflow->id,
            'resumed_from_step_id' => $startStepId,
            'steps' => [],
        ];

        $steps = $workflow->steps()->orderBy('step_order')->get();
        $startProcessing = false;
        foreach ($steps as $step) {
            if (!$startProcessing) {
                if ((int) $step->id === (int) $startStepId) {
                    $startProcessing = true;
                } else {
                    continue;
                }
            }

            // If we encounter another delay, schedule and stop again
            if (($step->delay_minutes ?? 0) > 0) {
                ExecutionLog::create([
                    'workflow_id' => $workflow->id,
                    'step_id' => $step->id,
                    'parent_execution_log_id' => $parentLog?->id,
                    'status' => 'scheduled',
                    'input_context' => $context,
                ]);
                RunWorkflowJob::dispatch($workflow->id, $context, $step->id)->delay(now()->addMinutes((int) $step->delay_minutes));
                $results['steps'][] = [
                    'step_id' => $step->id,
                    'status' => 'scheduled',
                    'delay_minutes' => (int) $step->delay_minutes,
                ];
                break;
            }

            $start = microtime(true);
            $execLog = ExecutionLog::create([
                'workflow_id' => $workflow->id,
                'step_id' => $step->id,
                'parent_execution_log_id' => $parentLog?->id,
                'status' => 'started',
                'input_context' => $context,
            ]);

            try {
                $handler = $this->resolveHandler($step);
                if (!$handler) {
                    throw new \RuntimeException("No handler for step type {$step->step_type}");
                }
                $out = $handler->handle($context, $step);
                $duration = (int) ((microtime(true) - $start) * 1000);
                $execLog->update([
                    'status' => 'success',
                    'raw_output' => $out['raw'] ?? ($out['output'] ?? null),
                    'parsed_output' => $out['parsed'] ?? null,
                    'duration_ms' => $duration,
                    'token_usage' => $out['token_usage'] ?? null,
                    'cost' => $out['cost'] ?? null,
                ]);
                if (!empty($out['context']) && is_array($out['context'])) {
                    $context = array_replace_recursive($context, $out['context']);
                }
                $results['steps'][] = [
                    'step_id' => $step->id,
                    'status' => 'success',
                    'duration_ms' => $duration,
                ];
            } catch (Throwable $e) {
                $execLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $results['steps'][] = [
                    'step_id' => $step->id,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Utility used by condition handler to execute a subset of steps
     */
    public function executeSteps(array $steps, Workflow $workflow, array $context = [], ?ExecutionLog $parentLog = null): array
    {
        $results = [];
        foreach ($steps as $step) {
            $start = microtime(true);
            $execLog = ExecutionLog::create([
                'workflow_id' => $workflow->id,
                'step_id' => $step->id,
                'parent_execution_log_id' => $parentLog?->id,
                'status' => 'started',
                'input_context' => $context,
            ]);
            try {
                $handler = $this->resolveHandler($step);
                if (!$handler) {
                    throw new \RuntimeException("No handler for step type {$step->step_type}");
                }
                $out = $handler->handle($context, $step);
                $duration = (int) ((microtime(true) - $start) * 1000);
                $execLog->update([
                    'status' => 'success',
                    'raw_output' => $out['raw'] ?? ($out['output'] ?? null),
                    'parsed_output' => $out['parsed'] ?? null,
                    'duration_ms' => $duration,
                    'token_usage' => $out['token_usage'] ?? null,
                    'cost' => $out['cost'] ?? null,
                ]);
                if (!empty($out['context']) && is_array($out['context'])) {
                    $context = array_replace_recursive($context, $out['context']);
                }
                $results[] = [
                    'step_id' => $step->id,
                    'status' => 'success',
                    'duration_ms' => $duration,
                ];
            } catch (Throwable $e) {
                $execLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $results[] = [
                    'step_id' => $step->id,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }
        return $results;
    }
}
