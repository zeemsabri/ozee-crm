<?php

namespace App\Services\StepHandlers;

use App\Models\Prompt;
use App\Models\WorkflowStep;
use App\Services\AIGenerationService;
use Illuminate\Support\Facades\Log;

class AiPromptStepHandler implements StepHandlerContract
{
    public function __construct(
        protected AIGenerationService $ai,
    ) {}

    public function handle(array $context, WorkflowStep $step): array
    {
        $prompt = $step->prompt ?: Prompt::find($step->prompt_id);
        if (!$prompt) {
            throw new \RuntimeException('Prompt not found for AI_PROMPT step');
        }

        $result = $this->ai->generate($prompt, $context);

        return [
            'raw' => $result['raw'] ?? null,
            'parsed' => $result['parsed'] ?? null,
            'token_usage' => $result['token_usage'] ?? null,
            'cost' => $result['cost'] ?? null,
            'context' => [
                'ai' => [
                    'last_output' => $result['parsed'] ?? $result['raw'] ?? null,
                ],
            ],
        ];
    }
}
