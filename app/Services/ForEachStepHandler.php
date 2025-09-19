<?php

namespace App\Services\StepHandlers;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;
use Illuminate\Support\Arr;

class ForEachStepHandler implements StepHandlerContract
{
    public function execute(Workflow $workflow, WorkflowStep $step, array &$context): array
    {
        $sourceArrayPath = $step->step_config['sourceArray'] ?? null;
        if (!$sourceArrayPath) {
            return ['status' => 'failed', 'message' => 'Source array not configured.'];
        }

        // Use the engine's templating to resolve the path to the array
        $engine = app(WorkflowEngineService::class);
        $resolvedArray = $engine->getTemplatedValue($sourceArrayPath, $context);

        if (!is_array($resolvedArray)) {
            return ['status' => 'skipped', 'message' => 'Source path did not resolve to an array.'];
        }

        $loopResults = [];
        $childSteps = $step->children; // Assumes 'children' relationship is loaded

        foreach ($resolvedArray as $index => $item) {
            // Create a temporary context for this iteration
            $iterationContext = $context;
            // Make the current item available under a special 'loop' key
            $iterationContext['loop'] = [
                'item' => $item,
                'index' => $index,
                'is_first' => $index === 0,
                'is_last' => $index === count($resolvedArray) - 1,
            ];

            // Execute the nested steps with the iteration context
            $engine->executeSteps($workflow, $childSteps, $iterationContext);
            $loopResults[] = $iterationContext['steps'] ?? []; // Collect results if needed
        }

        // After the loop, revert context to its pre-loop state but merge results
        $context['steps'] = array_merge($context['steps'] ?? [], Arr::flatten($loopResults));

        return [
            'status' => 'completed',
            'output' => [
                'iterations' => count($resolvedArray)
            ]
        ];
    }
}
