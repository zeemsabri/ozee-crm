<?php

namespace App\Jobs;

use App\Models\ExecutionLog;
use App\Models\Prompt;
use App\Models\Workflow;
use App\Services\AIGenerationService;
use App\Services\WorkflowEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAiContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180; // Give this job a longer timeout

    public $backoff = [60, 300, 900];

    public function __construct(
        public int $workflowId,
        public int $promptId,
        public int $currentStepId,
        public array $promptData,
        public array $context,
        public ExecutionLog $execLog,
        public array $resumeNextSiblingIds = []
    ) {}

    public function handle(AIGenerationService $aiService, WorkflowEngineService $engine): void
    {
        $workflow = Workflow::find($this->workflowId);
        $prompt = Prompt::find($this->promptId);

        if (! $workflow || ! $prompt) {
            return;
        }

        try {
            $start = microtime(true);

            Log::info(json_encode($this->promptData));
            // 1. Execute the AI generation
            $result = $aiService->generate($prompt, $this->promptData);
            $parsed = $result['parsed'] ?? null;
            if (! $parsed && is_string($result['raw'])) {
                $decoded = json_decode($result['raw'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $parsed = $decoded;
                }
            }

            // 2. Merge the AI output back into the context
            $this->context['ai']['last_output'] = $parsed ?? $result['raw'];
            // Also place the output in the context for the specific step that triggered it
            $result = $this->context['step_'.$this->currentStepId] = [
                'raw' => $result['raw'],
                'parsed' => $parsed,
                'token_usage' => $result['token_usage'] ?? null,
                'cost' => $result['cost'] ?? null,
            ];
            $this->context['steps'][$this->currentStepId] = $this->context['step_'.$this->currentStepId];

            // Execute any remaining sibling steps within the same container scope before resuming top-level
            if (! empty($this->resumeNextSiblingIds)) {
                $siblingSteps = $workflow->steps()
                    ->whereIn('id', $this->resumeNextSiblingIds)
                    ->orderBy('step_order', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
                $engine->executeSteps($siblingSteps, $workflow, $this->context, $this->execLog);
            }

            // 3. Determine resume point after the nearest top-level ancestor of the AI step
            $ancestorTopLevelId = $engine->findTopLevelAncestorId($workflow, $this->currentStepId);
            $nextStepId = $ancestorTopLevelId ? $engine->findNextTopLevelStepIdAfter($workflow, $ancestorTopLevelId) : null;

            $raw = $result['raw'] ?? null;
            $parsed = $result['parsed'] ?? null;
            if (! $parsed && is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $parsed = $decoded;
                }
            }

            $out = [
                'raw' => $raw,
                'parsed' => $parsed,
                'token_usage' => $result['token_usage'] ?? null,
                'cost' => $result['cost'] ?? null,
                'context' => [
                    'ai' => ['last_output' => $parsed ?? $raw],
                ],
            ];

            // 4. If there is a next step, dispatch a new job to continue the workflow
            //            if ($nextStepId) {
            //                RunWorkflowJob::dispatch(
            //                    $this->workflowId,
            //                    $this->context,
            //                    $nextStepId,
            //                    'wf:'.$this->workflowId.'|start:'.$nextStepId
            //                );
            //            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            $this->execLog->update([
                'status' => 'success',
                'raw_output' => $out['raw'] ?? ($out['output'] ?? null),
                'parsed_output' => $out['parsed'] ?? null,
                'duration_ms' => $duration,
                'token_usage' => $out['token_usage'] ?? null,
                'cost' => $out['cost'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->execLog->update([
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
