<?php

namespace App\Services\StepHandlers;

use App\Models\ExecutionLog;
use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Log;

class TransformContentStepHandler implements StepHandlerContract
{
    public function __construct(
        protected WorkflowEngineService $engine,
    ) {}

    public function handle(array $context, WorkflowStep $step, ExecutionLog|null $execLog = null): array
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

            case 'remove_html':
                // Convert HTML to plain text: strip tags and decode entities.
                $stripped = strip_tags($source);
                // Decode entities twice to handle nested encodings but avoid infinite loops
                $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $result = trim($decoded);
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

    /**
     * Implements a highly robust, line-by-line search for the marker.
     * It normalizes text before comparison to overcome encoding and character issues.
     */
    protected function removeAfterMarker(string $source, string $marker): string
    {
        Log::info('cleaning');
        // 1. Validate the marker
        $trimmedMarker = trim($marker);
        if ($trimmedMarker === '') {
            return $source;
        }

        // 2. Create a simplified, "normalized" version of the marker for reliable comparison.
        $normalizedMarker = $this->normalizeStringForComparison($trimmedMarker);
        if ($normalizedMarker === '') {
            return $source; // Marker might only contain characters that get stripped.
        }

        // 3. Split the original source into lines. Using a regex is best for mixed line endings.
        $originalLines = preg_split('/(\r\n|\n|\r)/', $source);
        $linesToKeep = [];
        $markerFound = false;

        // 4. Go through each original line to find the marker.
        foreach ($originalLines as $line) {
            $linesToKeep[] = $line;

            // 5. Normalize the current line to compare it with the normalized marker.
            $normalizedLine = $this->normalizeStringForComparison($line);

            // 6. Perform the check. str_contains is a clear and direct way to do this.
            if (str_contains($normalizedLine, $normalizedMarker)) {
                $markerFound = true;
                break; // Exit the loop as soon as the target line is found and included.
            }
        }

        if (!$markerFound) {
            return $source;
        }

        // 7. Join the original lines we've collected back into a string.
        return trim(implode(PHP_EOL, $linesToKeep));
    }

    /**
     * Helper function to simplify a string for reliable comparison by removing
     * accents, special characters, and standardizing whitespace.
     */
    private function normalizeStringForComparison(string $str): string
    {
        // Suppress warnings for invalid characters in iconv.
        $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        if ($normalized === false) {
            $normalized = $str; // Fallback to original if iconv fails.
        }
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }
}
