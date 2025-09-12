<?php

namespace App\Services;

use App\Models\Prompt;
use Illuminate\Support\Facades\Log;

class AIGenerationService
{
    /**
     * Very lightweight placeholder-based templating, replacing {{ path.to.value }} using $variables
     */
    protected function renderTemplate(string $text, array $variables): string
    {
        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($matches) use ($variables) {
            $path = trim($matches[1]);
            $parts = preg_split('/\.|\:/', $path);
            $val = $variables;
            foreach ($parts as $p) {
                if (is_array($val) && array_key_exists($p, $val)) {
                    $val = $val[$p];
                } else {
                    return '';
                }
            }
            return is_scalar($val) ? (string) $val : json_encode($val);
        }, $text);
    }

    /**
     * Stub Gemini call - logs request and returns a simple structured output
     */
    public function generate(Prompt $prompt, array $variables): array
    {
        $system = $this->renderTemplate($prompt->system_prompt_text, $variables);

        // In a real implementation, call Gemini here. For now, log and return stubbed output.
        Log::info('AIGenerationService.generate', [
            'prompt_id' => $prompt->id,
            'model' => $prompt->model_name,
            'system' => mb_strimwidth($system, 0, 500, '...'),
            'generation_config' => $prompt->generation_config,
        ]);

        $text = '[AI OUTPUT] ' . substr(md5($system . json_encode($variables)), 0, 12);
        return [
            'raw' => [
                'model' => $prompt->model_name,
                'text' => $text,
            ],
            'parsed' => [
                'text' => $text,
            ],
            'token_usage' => 100,
            'cost' => 0.0005,
        ];
    }
}
