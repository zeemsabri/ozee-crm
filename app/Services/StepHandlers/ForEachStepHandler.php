<?php

namespace App\Services\StepHandlers;

use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;

class ForEachStepHandler implements StepHandlerContract
{
    public function __construct(
        protected WorkflowEngineService $engine,
    ) {}

    public function handle(array $context, WorkflowStep $step): array
    {
        $cfg = $step->step_config ?? [];
        $source = $cfg['sourceArray'] ?? null;
        if (!$source) {
            throw new \InvalidArgumentException('FOR_EACH step requires step_config.sourceArray');
        }

        // Resolve the array from context (supports tokens like {{step_2.records}})
        $resolved = $this->engine->getTemplatedValue($source, $context);
        if (!is_array($resolved)) {
            // If it resolves to a JSON-string array, try decoding
            if (is_string($resolved)) {
                $decoded = json_decode($resolved, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $resolved = $decoded;
                }
            }
        }
        if (!is_array($resolved)) {
            // Not an array; skip gracefully
            return [
                'parsed' => [ 'iterations' => 0 ],
                'context' => [],
            ];
        }

        // Discover child steps: use relation if available; otherwise, reconstruct from flat list via parent markers
        $children = [];
        if (isset($step->children) && is_iterable($step->children)) {
            $children = $step->children;
        } else {
            $all = $step->workflow?->steps()->orderBy('step_order')->get() ?? collect();
            foreach ($all as $cand) {
                $cfgCand = $cand->step_config ?? [];
                if (($cfgCand['_parent_id'] ?? null) == $step->id && empty($cfgCand['_branch'])) {
                    $children[] = $cand;
                }
            }
        }

        $total = count($resolved);
        foreach ($resolved as $index => $item) {
            $iterationContext = $context;
            $iterationContext['loop'] = [
                'item' => $item,
                'index' => $index,
                'is_first' => $index === 0,
                'is_last' => $index === ($total - 1),
            ];
            // Execute children within the iteration context
            $this->engine->executeSteps($children, $step->workflow, $iterationContext);
        }

        return [
            'parsed' => [ 'iterations' => $total ],
        ];
    }
}
