<?php

namespace App\Services\StepHandlers;

use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;

class TransformContentStepHandler implements StepHandlerContract
{
    public function __construct(
        protected WorkflowEngineService $engine,
    ) {}

    public function handle(array $context, WorkflowStep $step): array
    {
        $cfg = $step->step_config ?? [];
        $type = (string)($cfg['type'] ?? '');

        if ($type === '') {
            throw new \InvalidArgumentException('TRANSFORM_CONTENT step requires step_config.type');
        }

        // Resolve source content (can be a token like {{trigger.body}})
        $source = $this->engine->getTemplatedValue($cfg['source'] ?? '', $context);
        if (!is_string($source)) {
            // Try to stringify non-string values to avoid fatal errors and be permissive
            $source = is_scalar($source) ? (string) $source : json_encode($source);
        }

        $result = $source;

        switch ($type) {
            case 'remove_after_marker':
                $marker = $this->engine->getTemplatedValue($cfg['marker'] ?? '', $context);
                if (!is_string($marker)) {
                    $marker = is_scalar($marker) ? (string) $marker : json_encode($marker);
                }
                $result = $this->removeAfterMarker($source, $marker);
                break;

            case 'find_and_replace':
                $find = (string) $this->engine->getTemplatedValue($cfg['find'] ?? '', $context);
                $replace = (string) $this->engine->getTemplatedValue($cfg['replace'] ?? '', $context);
                $result = str_replace($find, $replace, $source);
                break;

            default:
                throw new \InvalidArgumentException("Unknown transformation type: {$type}");
        }

        return [
            'parsed' => [
                'type' => $type,
                'result' => $result,
                // Friendly alias for downstream usage
                'cleaned_body' => $result,
                // Lightweight schema to help UI token pickers (optional)
                'schema' => [
                    'cleaned_body' => 'Text',
                    'result' => 'Text',
                ],
            ],
            // Optional: mirror under a namespaced context as well
            'context' => [ 'transform' => [ 'step_' . $step->id => [ 'result' => $result, 'cleaned_body' => $result ] ] ],
        ];
    }

    protected function removeAfterMarker(string $source, string $marker): string
    {
        if ($marker === '') return $source; // no-op
        $pos = mb_stripos($source, $marker); // case-insensitive search
        if ($pos === false) return $source;
        return mb_substr($source, 0, $pos);
    }
}
