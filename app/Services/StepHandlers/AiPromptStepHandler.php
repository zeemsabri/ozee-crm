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

        $raw = $result['raw'] ?? null;
        $parsed = $result['parsed'] ?? null;
        if (!$parsed && is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parsed = $decoded;
            }
        }

        // Optional response mapping: map parsed keys into context as simple variables
        $mapping = $step->step_config['response_mapping'] ?? [];
        $mappedOut = [];
        if (is_array($mapping) && is_array($parsed)) {
            // Support two formats: ['alias' => 'path.to.key'] or [['alias' => 'x', 'path' => 'a.b']]
            foreach ($mapping as $k => $v) {
                if (is_array($v)) {
                    $alias = $v['alias'] ?? ($v['to'] ?? null);
                    $path = $v['path'] ?? ($v['from'] ?? null);
                } else {
                    $alias = is_string($k) ? $k : (is_string($v) ? $v : null);
                    $path = is_string($v) ? $v : (is_string($k) ? $k : null);
                }
                if (!$alias || !$path) continue;
                $val = $this->getFromArrayByPath($parsed, $path);
                $mappedOut[$alias] = $val;
            }
        }

        $contextOut = [
            'ai' => [
                'last_output' => $parsed ?? $raw,
            ],
        ];
        if (!empty($mappedOut)) {
            $contextOut = array_replace_recursive($contextOut, $mappedOut);
        }

        return [
            'raw' => $raw,
            'parsed' => $parsed,
            'token_usage' => $result['token_usage'] ?? null,
            'cost' => $result['cost'] ?? null,
            'context' => $contextOut,
        ];
    }

    protected function getFromArrayByPath(array $data, string $path)
    {
        $parts = preg_split('/\.|\:/', $path);
        $val = $data;
        foreach ($parts as $p) {
            if (is_array($val) && array_key_exists($p, $val)) {
                $val = $val[$p];
            } else {
                return null;
            }
        }
        return $val;
    }
}
