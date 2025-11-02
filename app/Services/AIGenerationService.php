<?php

namespace App\Services;

use App\Models\Prompt;
use Illuminate\Support\Facades\Http;
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
     * Call Gemini API to generate content based on the provided prompt and variables.
     */
    public function generate(Prompt $prompt, array $variables): array
    {
        $apiKey = config('services.gemini.key');
        if (! $apiKey) {
            throw new \RuntimeException('Gemini API key is not configured.');
        }

        $model = $prompt->model_name ?: config('services.gemini.model', 'gemini-2.5-flash-preview-05-20');
        $system = $this->renderTemplate($prompt->system_prompt_text ?? '', $variables);

        $system = $system.' '.json_encode($prompt->response_json_template);

        // Build payload
        $generationConfig = is_array($prompt->generation_config) ? $prompt->generation_config : [];
        if (! isset($generationConfig['responseMimeType'])) {
            // Favor JSON so downstream parsing works in workflows
            $generationConfig['responseMimeType'] = 'application/json';
        }

        $payload = [
            'contents' => [
                [
                    'parts' => [[
                        // Provide the variables as JSON for the model to consume deterministically
                        'text' => json_encode($variables),
                    ]],
                ],
            ],
            'systemInstruction' => [
                'parts' => [['text' => $system]],
            ],
            'generationConfig' => $generationConfig,
        ];

        //        Log::info(json_encode($payload));

        $url = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', $model, $apiKey);

        $response = Http::post($url, $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to communicate with Gemini API. Status: '.$response->status().' Body: '.$response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text', '');
        $parsed = null;
        if (is_string($text) && $text !== '') {
            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parsed = $decoded;
            }
        }

        $usage = $response->json('usageMetadata');
        if (! is_array($usage)) {
            $usage = [
                'promptTokenCount' => null,
                'candidatesTokenCount' => null,
                'totalTokenCount' => null,
            ];
        }

        return [
            'raw' => $text,
            'parsed' => $parsed,
            'token_usage' => $usage,
            'cost' => null, // Leave null to avoid misleading cost calculations
        ];
    }
}
