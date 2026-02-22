<?php

namespace App\Services\StepHandlers;

use App\Models\ExecutionLog;
use App\Models\Prompt;
use App\Models\WorkflowStep;
use App\Services\AIGenerationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiPromptStepHandler implements StepHandlerContract
{
    public function __construct(
        protected AIGenerationService $ai,
    ) {}

    public function handle(array $context, WorkflowStep $step, ?ExecutionLog $execLog = null): array
    {
        $cfg = $step->step_config ?? [];

        $prompt = $this->resolvePrompt($step, $cfg);
        if (! $prompt) {
            throw new \RuntimeException('Prompt not found for AI_PROMPT step');
        }

        $promptData = $this->gatherPromptData($context, $cfg);
        Log::info(json_encode($promptData));

        // --- REPLACEMENT ---
        // Instead of calling the service directly, dispatch the async job.
        $nextSiblingIds = $context['_resume_next_sibling_ids'] ?? [];
        \App\Jobs\GenerateAiContentJob::dispatch(
            $step->workflow_id,
            $prompt->id,
            $step->id,
            $promptData,
            $context,
            $execLog,
            $nextSiblingIds
        );
        // --- END REPLACEMENT ---

        return [
            'parsed' => ['status' => 'AI_JOB_DISPATCHED'],
            'context' => [], // IMPORTANT: Do not merge any context here
        ];
    }

    protected function resolvePrompt(WorkflowStep $step, array $config): ?Prompt
    {
        if ($step->prompt) {
            return $step->prompt;
        }
        $promptId = $config['promptRef']['id'] ?? null;
        if ($promptId) {
            return Prompt::find($promptId);
        }
        // Fallback for inline/hardcoded prompts in the config.
        if (! empty($config['prompt']) && is_string($config['prompt'])) {
            return new Prompt(['system_prompt_text' => $config['prompt']]);
        }

        return null;
    }

    /**
     * Gathers data for the AI prompt from the context based on step configuration.
     * This version includes fixes for input parsing and model detection.
     */
    protected function gatherPromptData(array $context, array $config): array
    {
        $promptData = [];
        $loopItem = $context['loop']['item'] ?? null;
        $triggerData = $context['trigger'] ?? [];

        // If user provided a free-text body (can include tokens), resolve and prioritize it under 'body'
        $freeText = trim((string) ($config['freeText'] ?? ''));
        if ($freeText !== '') {
            try {
                /** @var \App\Services\WorkflowEngineService $engine */
                $engine = app(\App\Services\WorkflowEngineService::class);
                $resolved = $engine->getTemplatedValue($freeText, $context);
                // Ensure scalar string for prompt input
                if (! is_string($resolved)) {
                    $resolved = is_scalar($resolved) ? (string) $resolved : json_encode($resolved);
                }
                $promptData['context_data'] = $resolved;
            } catch (\Throwable $e) {
                // Fallback: use raw freeText if engine not available
                $promptData['body'] = $freeText;
            }
        }

        // First, determine the primary model within the trigger context.
        $baseModelName = $this->resolveModelFromContext($context);
        $baseModelKey = $baseModelName ? strtolower($baseModelName) : null;

        // 1. Gather direct inputs from the determined source.
        $inputs = $config['aiInputs'] ?? [];
        foreach ($inputs as $input) {
            $source = 'trigger'; // Default source
            $path = $input;

            if (str_contains($input, ':')) {
                [$source, $path] = explode(':', $input, 2);
            }

            $dataRoot = ($source === 'loop' && $loopItem) ? $loopItem : $triggerData;
            $key = last(explode('.', $path));

            if ($key) {
                $value = null;
                // FIX: First, try to get data from within the primary model object (e.g., from 'trigger.email.subject')
                if ($source === 'trigger' && $baseModelKey) {
                    $value = Arr::get($dataRoot, $baseModelKey.'.'.$path);
                }

                // Fallback: If not found, try the path from the root of the data source (e.g., 'trigger.event')
                if ($value === null) {
                    $value = Arr::get($dataRoot, $path);
                }
                $promptData[$key] = $value;
            }
        }

        // 2. Gather related data if configured.
        $baseModelId = $context['triggering_object_id'] ?? ($triggerData[$baseModelKey]['id'] ?? null);

        $relationsConfig = $config['relationships'] ?? null;
        if ($relationsConfig && $baseModelName && $baseModelId) {
            // Standard case: event-driven trigger — re-query via Eloquent
            $relationsConfig['base_model'] = $baseModelName;
            $relatedData = $this->gatherRelatedData($relationsConfig, (int) $baseModelId);
            if (! empty($relatedData)) {
                $promptData['with'] = $relatedData;
            }
        } elseif ($relationsConfig && $loopItem && ! empty($relationsConfig['roots'])) {
            // Loop-context fallback: data is already pre-loaded in loop.item (e.g. schedule + FETCH_RECORDS with relationships).
            // Pull the selected roots directly from the loop item instead of doing another DB query.
            $relatedData = [];
            foreach ($relationsConfig['roots'] as $rootName) {
                if (array_key_exists($rootName, $loopItem)) {
                    $rootData = $loopItem[$rootName];

                    // Apply field filtering if specified
                    $selectedFields = $relationsConfig['fields'][$rootName] ?? null;
                    if ($selectedFields && $selectedFields !== ['*'] && is_array($rootData)) {
                        if (isset($rootData[0])) {
                            // Collection: filter each item
                            $rootData = array_map(function ($item) use ($selectedFields) {
                                return is_array($item) ? Arr::only($item, $selectedFields) : $item;
                            }, $rootData);
                        } else {
                            // Single object
                            $rootData = Arr::only($rootData, $selectedFields);
                        }
                    }

                    // Include nested relationships if they are already loaded in the item
                    $nestedRoots = $relationsConfig['nested'][$rootName] ?? [];
                    foreach ($nestedRoots as $nestedName) {
                        // Nested data is typically already present on the root items (eager-loaded by FETCH_RECORDS)
                        // No additional processing needed; the data is already part of $rootData
                    }

                    $relatedData[$rootName] = $rootData;
                }
            }
            if (! empty($relatedData)) {
                $promptData['with'] = $relatedData;
            }
        }

        return $promptData;
    }

    protected function gatherRelatedData(array $relationsConfig, int $baseModelId): array
    {
        $baseModelClass = $this->resolveModelClass($relationsConfig['base_model']);
        if (! $baseModelClass) {
            return [];
        }

        $query = $baseModelClass::query();
        $eagerLoad = [];

        $roots = $relationsConfig['roots'] ?? [];
        $fields = $relationsConfig['fields'] ?? [];
        $nested = $relationsConfig['nested'] ?? [];

        foreach ($roots as $rootRelation) {
            $nestedRelations = $nested[$rootRelation] ?? [];
            $eagerLoad[$rootRelation] = function ($q) use ($rootRelation, $nestedRelations, $fields) {
                $rootFields = $fields[$rootRelation] ?? ['*'];
                if ($rootFields !== ['*'] && ! in_array('id', $rootFields)) {
                    $rootFields[] = 'id';
                }
                $q->select($rootFields);

                foreach ($nestedRelations as $nestedRelation) {
                    $nestedPath = "{$rootRelation}.{$nestedRelation}";
                    $nestedFields = $fields[$nestedPath] ?? ['*'];
                    if ($nestedFields !== ['*'] && ! in_array('id', $nestedFields)) {
                        $nestedFields[] = 'id';
                    }
                    $q->with([$nestedRelation => fn ($nq) => $nq->select($nestedFields)]);
                }
            };
        }

        if (empty($eagerLoad)) {
            return [];
        }

        $modelInstance = $query->with($eagerLoad)->find($baseModelId);

        return $modelInstance ? $modelInstance->getRelations() : [];
    }

    protected function resolveModelClass(string $name): ?string
    {
        $baseName = Str::studly(class_basename($name));
        $candidates = [$name, 'App\\Models\\'.$baseName];
        foreach ($candidates as $c) {
            if (class_exists($c)) {
                return $c;
            }
        }

        return null;
    }

    /**
     * Accurately determines the primary model from the workflow context.
     * This version uses the triggering_object_id for more reliable detection.
     */
    private function resolveModelFromContext(array $context): ?string
    {
        if (empty($context)) {
            return null;
        }

        $triggeringId = $context['triggering_object_id'] ?? null;
        $triggerContext = $context['trigger'] ?? $context;

        // Best case - Find the model whose ID matches the triggering object ID.
        if ($triggeringId) {
            foreach ($triggerContext as $key => $value) {
                if (is_array($value) && isset($value['id']) && (string) $value['id'] === (string) $triggeringId) {
                    return Str::studly($key);
                }
            }
        }

        // Fallback: Infer from event name, e.g., "email.created" -> "Email"
        $eventName = $context['event'] ?? ($triggerContext['event'] ?? null);
        if ($eventName && str_contains($eventName, '.')) {
            return Str::studly(explode('.', $eventName)[0]);
        }

        return null;
    }
}
