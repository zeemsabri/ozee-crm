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

    public function handle(array $context, WorkflowStep $step, ExecutionLog|null $execLog = null): array
    {
        $cfg = $step->step_config ?? [];

        $prompt = $this->resolvePrompt($step, $cfg);
        if (!$prompt) {
            throw new \RuntimeException('Prompt not found for AI_PROMPT step');
        }

//        $promptData = $this->gatherPromptData($context, $cfg);

//        $result = $this->ai->generate($prompt, $promptData);

        $promptData = $this->gatherPromptData($context, $cfg);

        // --- REPLACEMENT ---
        // Instead of calling the service directly, dispatch the async job.
        \App\Jobs\GenerateAiContentJob::dispatch(
            $step->workflow_id,
            $prompt->id,
            $step->id,
            $promptData,
            $context,
            $execLog
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
        if (!empty($config['prompt']) && is_string($config['prompt'])) {
            return new Prompt(['system_prompt_text' => $config['prompt']]);
        }
        return null;
    }

    protected function gatherPromptData(array $context, array $config): array
    {
        $promptData = [];
        $baseModel = null;
        $baseModelId = null;

        // Determine the primary data source (loop or trigger).
        $loopItem = $context['loop']['item'] ?? null;
        $triggerData = $context['trigger'] ?? [];

        if ($loopItem) {
            $baseModel = $this->resolveModelFromContext($loopItem);
            $baseModelId = $loopItem['id'] ?? null;
        } else {
            $baseModel = $this->resolveModelFromContext($triggerData);
            // Ensure $baseModel is not null before using it as an array key.
            $baseModelKey = $baseModel ? strtolower($baseModel) : '';
            $baseModelId = $context['triggering_object_id'] ?? ($triggerData[$baseModelKey]['id'] ?? null);
        }

        // 1. Gather direct inputs from the determined source.
        $inputs = $config['aiInputs'] ?? [];
        foreach ($inputs as $input) {
            [$source, $path] = explode(':', $input, 2) + ['trigger', ''];
            $dataRoot = ($source === 'loop' && $loopItem) ? $loopItem : $triggerData;
            $key = last(explode('.', $path));
            $promptData[$key] = Arr::get($dataRoot, $path);
        }

        // 2. Gather related data if configured.
        $relationsConfig = $config['relationships'] ?? null;
        // THE FIX: Ensure $baseModel is a valid string before proceeding.
        if ($relationsConfig && $baseModel && $baseModelId) {
            // Ensure the base_model in the config matches our context.
            $relationsConfig['base_model'] = $baseModel;
            $relatedData = $this->gatherRelatedData($relationsConfig, $baseModelId);
            if (!empty($relatedData)) {
                $promptData['with'] = $relatedData;
            }
        }

        return $promptData;
    }

    protected function gatherRelatedData(array $relationsConfig, int $baseModelId): array
    {
        $baseModelClass = $this->resolveModelClass($relationsConfig['base_model']);
        if (!$baseModelClass) {
            Log::warning("AI_PROMPT: Base model class not found for '{$relationsConfig['base_model']}'.");
            return [];
        }

        $query = $baseModelClass::query();
        $eagerLoad = [];

        $roots = $relationsConfig['roots'] ?? [];
        $fields = $relationsConfig['fields'] ?? [];
        $nested = $relationsConfig['nested'] ?? [];

        foreach ($roots as $rootRelation) {
            $nestedRelations = $nested[$rootRelation] ?? [];
            // THE FIX: Pass $rootRelation into the closure's `use` statement.
            $eagerLoad[$rootRelation] = function ($q) use ($rootRelation, $nestedRelations, $fields) {
                // THE FIX: Apply field selections to the top-level relation itself.
                $rootFields = $fields[$rootRelation] ?? ['*'];
                if ($rootFields !== ['*'] && !in_array('id', $rootFields)) {
                    $rootFields[] = 'id';
                }
                $q->select($rootFields);

                // Load nested relations with their specific field selections.
                foreach ($nestedRelations as $nestedRelation) {
                    $nestedPath = "{$rootRelation}.{$nestedRelation}";
                    $nestedFields = $fields[$nestedPath] ?? ['*'];
                    if ($nestedFields !== ['*'] && !in_array('id', $nestedFields)) $nestedFields[] = 'id';
                    $q->with([$nestedRelation => fn($nq) => $nq->select($nestedFields)]);
                }
            };
        }

        if (empty($eagerLoad)) return [];

        $modelInstance = $query->with($eagerLoad)->find($baseModelId);
        // No need to call filterRelations anymore as selections are done in the query.
        return $modelInstance ? $modelInstance->getRelations() : [];
    }

    protected function resolveModelClass(string $name): ?string
    {
        $candidates = [ $name, 'App\\Models\\' . $name ];
        foreach ($candidates as $c) {
            if (class_exists($c)) return $c;
        }
        return null;
    }

    private function resolveModelFromContext(array $context): ?string
    {
        if (empty($context)) return null;
        // Heuristic: find the key that holds the main model data.
        $keys = array_keys($context);
        foreach ($keys as $key) {
            if (is_array($context[$key]) && isset($context[$key]['id'])) {
                // Assuming the key is the lowercase model name.
                return Str::studly($key);
            }
        }
        return null;
    }
}

