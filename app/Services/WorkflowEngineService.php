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
use App\Services\StepHandlers\QueryDataStepHandler;
use App\Services\StepHandlers\SyncRelationshipStepHandler;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Services\StepHandlers\ForEachStepHandler;
use App\Services\StepHandlers\TransformContentStepHandler;
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
            'ACTION_SYNC_RELATIONSHIP' => new SyncRelationshipStepHandler($this),
            'ACTION_SEND_EMAIL' => new SendEmailStepHandler($this),
            'QUERY_DATA' => new QueryDataStepHandler(),
            'FETCH_RECORDS' => new QueryDataStepHandler(),
            'FOR_EACH' => new ForEachStepHandler($this),
            'TRANSFORM_CONTENT' => new TransformContentStepHandler($this),
            'ACTION_PROCESS_EMAIL' => new \App\Services\StepHandlers\ProcessEmailStepHandler($this),
            // TRIGGER steps are structural; at runtime they are a no-op
            'TRIGGER' => new class implements StepHandlerContract {
                public function handle(array $context, WorkflowStep $step, ExecutionLog|null $execLog = null): array
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
                public function handle(array $context, WorkflowStep $step, ExecutionLog|null $execLog = null): array
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

        Log::info('WorkflowEngineService.execute', [
            'context'   =>  $context
        ]);
        // Track if the incoming context was empty (useful for schedule-run guard)
        $initiallyEmpty = empty($context);
        // Seed a trigger namespace if not present for variable paths like {{ trigger.* }}
        if (!isset($context['trigger'])) {
            $context['trigger'] = $context;
        }
        $results = [
            'workflow_id' => $workflow->id,
            'steps' => [],
        ];

        $steps = $workflow->steps()->orderBy('step_order')->orderBy('id')->get();
        $topLevel = $steps->filter(function ($s) {
            $cfg = $s->step_config ?? [];
            return empty($cfg['_parent_id']);
        })->values();

        // Schedule-run guard: When started by schedule with empty context, enforce first non-trigger step is FETCH_RECORDS
        if (($workflow->trigger_event ?? null) === 'schedule.run' && $initiallyEmpty) {
            $firstNonTrigger = $steps->first(function ($s) {
                return strtoupper($s->step_type) !== 'TRIGGER';
            });
            if ($firstNonTrigger && strtoupper($firstNonTrigger->step_type) !== 'FETCH_RECORDS') {
                $message = 'Schedule-based workflow must start with a Fetch Records step.';
                Log::error('WorkflowEngineService.execute.schedule_guard', [
                    'workflow_id' => $workflow->id,
                    'message' => $message,
                ]);
                $results['error'] = $message;
                return $results;
            }
        }

        foreach ($steps as $step) {
            // Skip nested steps (children of containers like CONDITION). Their execution is handled by the parent handler via executeSteps().
            $cfg = $step->step_config ?? [];
            if (!empty($cfg['_parent_id'])) {
                continue;
            }
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
//
            try {
                $handler = $this->resolveHandler($step);

                if (!$handler) {
                    throw new \RuntimeException("No handler for step type {$step->step_type}");
                }

                // Compute remaining top-level siblings for resume hinting
                $idxTop = $topLevel->search(fn ($s) => (int)$s->id === (int)$step->id);
                $remainingTop = $idxTop !== false ? $topLevel->slice($idxTop + 1) : collect();
                $remainingIds = $remainingTop->pluck('id')->map(fn($v) => (int) $v)->values()->all();
                $ctxForHandler = $context;
                $ctxForHandler['_resume_next_sibling_ids'] = $remainingIds;

                $out = $handler->handle($ctxForHandler, $step, $execLog);

                if (($out['parsed']['status'] ?? null) === 'AI_JOB_DISPATCHED') {
                    $results['steps'][] = [
                        'step_id' => $step->id,
                        'status' => 'delegated_async',
                    ];
                    // Stop processing further steps in this job.
                    break;
                }

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
                // Store parsed output under step-specific keys for downstream steps
                if (isset($out['parsed'])) {
                    $context['step_' . $step->id] = $out['parsed'];
                    $context['steps'] = $context['steps'] ?? [];
                    $context['steps'][$step->id] = $out['parsed'];
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
        if (!isset($context['trigger'])) {
            $context['trigger'] = $context;
        }
        $results = [
            'workflow_id' => $workflow->id,
            'resumed_from_step_id' => $startStepId,
            'steps' => [],
        ];

        $steps = $workflow->steps()->orderBy('step_order')->orderBy('id')->get();
        $topLevel = $steps->filter(function ($s) {
            $cfg = $s->step_config ?? [];
            return empty($cfg['_parent_id']);
        })->values();
        $startProcessing = false;
        foreach ($steps as $step) {
            if (!$startProcessing) {
                if ((int) $step->id === (int) $startStepId) {
                    $startProcessing = true;
                } else {
                    continue;
                }
            }

            // Skip nested steps; only top-level steps are executed in this traversal.
            $cfg = $step->step_config ?? [];
            if (!empty($cfg['_parent_id'])) {
                continue;
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
                // Compute remaining top-level siblings for resume hinting
                $idxTop = $topLevel->search(fn ($s) => (int)$s->id === (int)$step->id);
                $remainingTop = $idxTop !== false ? $topLevel->slice($idxTop + 1) : collect();
                $remainingIds = $remainingTop->pluck('id')->map(fn($v) => (int) $v)->values()->all();
                $ctxForHandler = $context;
                $ctxForHandler['_resume_next_sibling_ids'] = $remainingIds;

                $out = $handler->handle($ctxForHandler, $step, $execLog);
                if (($out['parsed']['status'] ?? null) === 'AI_JOB_DISPATCHED') {
                    $results['steps'][] = [
                        'step_id' => $step->id,
                        'status' => 'delegated_async',
                    ];
                    // Stop processing further steps in this job.
                    break;
                }
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
                if (isset($out['parsed'])) {
                    $context['step_' . $step->id] = $out['parsed'];
                    $context['steps'] = $context['steps'] ?? [];
                    $context['steps'][$step->id] = $out['parsed'];
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
     * Determine the next top-level step ID after the given step in a deterministic order.
     * Order: step_order ASC, id ASC. Returns null if current is last or not found.
     */
    public function findNextTopLevelStepId(Workflow $workflow, int $currentStepId): ?int
    {
        $ordered = $workflow->steps()
            ->orderBy('step_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $idx = $ordered->search(fn ($s) => (int)$s->id === (int)$currentStepId);
        if ($idx === false) {
            return null;
        }
        $next = $ordered->get($idx + 1);
        return $next?->id;
    }

    /**
     * Walk up the parent chain (via step_config._parent_id) to find the nearest top-level ancestor.
     * If the given step is already top-level, its own ID is returned. Returns null if the step is missing.
     */
    public function findTopLevelAncestorId(Workflow $workflow, int $stepId): ?int
    {
        $step = $workflow->steps()->where('id', $stepId)->first();
        if (!$step) return null;

        $guard = 0;
        while ($guard < 50) {
            $cfg = $step->step_config ?? [];
            $parentId = $cfg['_parent_id'] ?? null;
            if (!$parentId) {
                return (int) $step->id;
            }
            $parent = $workflow->steps()->where('id', (int)$parentId)->first();
            if (!$parent) {
                // Broken parent chain; treat the original as top-level to avoid null resume.
                return (int) $stepId;
            }
            $step = $parent;
            $guard++;
        }
        return (int) $stepId; // Safety fallback
    }

    /**
     * Determine the next top-level step after the provided top-level step ID.
     * Uses deterministic ordering (step_order ASC, id ASC) and filters out nested steps.
     */
    public function findNextTopLevelStepIdAfter(Workflow $workflow, int $topLevelStepId): ?int
    {
        $orderedTop = $workflow->steps()
            ->orderBy('step_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->filter(function ($s) {
                $cfg = $s->step_config ?? [];
                return empty($cfg['_parent_id']);
            })
            ->values();

        $idx = $orderedTop->search(fn ($s) => (int)$s->id === (int)$topLevelStepId);
        if ($idx === false) return null;
        $next = $orderedTop->get($idx + 1);
        return $next?->id;
    }

    /**
     * Utility used by condition handler to execute a subset of steps
     */
    public function executeSteps(iterable $steps, Workflow $workflow, array $context = [], ?ExecutionLog $parentLog = null): array
    {
        if (!isset($context['trigger'])) {
            $context['trigger'] = $context;
        }
        $results = [];

        // Normalize iterable to an indexed list so we can compute remaining siblings deterministically
        $list = is_array($steps) ? array_values($steps) : collect($steps)->values()->all();

        foreach ($list as $idx => $step) {
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

                // Compute remaining sibling IDs within this scope
                $remaining = array_slice($list, $idx + 1);
                $remainingIds = array_map(fn($s) => (int) $s->id, $remaining);
                $ctxForHandler = $context;
                $ctxForHandler['_resume_next_sibling_ids'] = $remainingIds;

                $out = $handler->handle($ctxForHandler, $step, $execLog);
                if (($out['parsed']['status'] ?? null) === 'AI_JOB_DISPATCHED') {
                    $results['steps'][] = [
                        'step_id' => $step->id,
                        'status' => 'delegated_async',
                    ];
                    // Stop processing further steps in this job.
                    break;
                }
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
                if (isset($out['parsed'])) {
                    $context['step_' . $step->id] = $out['parsed'];
                    $context['steps'] = $context['steps'] ?? [];
                    $context['steps'][$step->id] = $out['parsed'];
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

    // Resolve template tokens against context, returning native types when the whole value is a single token
    public function getTemplatedValue($value, array $context)
    {
        if (is_array($value)) {
            return array_map(fn($v) => $this->getTemplatedValue($v, $context), $value);
        }
        if (!is_string($value)) {
            return $value;
        }
        // If the string is exactly one token, return the raw value (could be array/object)
        if (preg_match('/^\s*{{\s*([^}]+)\s*}}\s*$/', $value, $m)) {
            $path = trim($m[1]);
            return $this->getFromContextPath($context, $path);
        }
        // Otherwise, interpolate tokens into the string
        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($m) use ($context) {
            $path = trim($m[1]);
            $val = $this->getFromContextPath($context, $path);
            if (is_scalar($val) || $val === null) return (string) $val;
            return json_encode($val);
        }, $value);
    }

    // This function should replace the existing getFromContextPath method
// in your App\Services\WorkflowEngineService.php file.

    protected function getFromContextPath(array $context, string $path)
    {
        if ($path === '') {
            return null;
        }

        // Use the original logic to split the path by either a dot or a colon.
        $originalParts = preg_split('/\.|\:/', $path);

        // --- Attempt 1: Try the original path exactly as the old function did ---
        $value = $context;
        $isFound = true;
        foreach ($originalParts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                $isFound = false;
                break;
            }
        }

        // If the original path was valid, return the value immediately.
        if ($isFound) {
            return $value;
        }

        // --- Attempt 2: If not found, try the fallback path with ".parsed" ---
        if (count($originalParts) > 1) {
            // Construct the fallback parts, e.g., ['step_115', 'parsed', 'remove_after']
            $fallbackParts = array_merge(
                [$originalParts[0]],
                ['parsed'],
                array_slice($originalParts, 1)
            );

            $fallbackValue = $context;
            $isFallbackFound = true;
            foreach ($fallbackParts as $part) {
                if (is_array($fallbackValue) && array_key_exists($part, $fallbackValue)) {
                    $fallbackValue = $fallbackValue[$part];
                } else {
                    $isFallbackFound = false;
                    break;
                }
            }

            if ($isFallbackFound) {
                return $fallbackValue;
            }
        }

        // If neither the original nor the fallback path worked, return null.
        return null;
    }
}
