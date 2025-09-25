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
                // Support both `marker` and `remove_after` keys for configuration flexibility
                $rawMarker = $cfg['marker'] ?? ($cfg['remove_after'] ?? '');
                $marker = $this->engine->getTemplatedValue($rawMarker, $context);
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
//        $marker = $request->input('marker');
//        $source = $request->input('source');

        Log::info('marker: ' . $marker);
        Log::info('source: ' . $source);
        // --- Step 1: Prepare and validate the marker ---
        $cleanMarker = trim($marker);

        // If the marker is empty after trimming, we can't search for it.
        if ($cleanMarker === '') {
            return $source;
        }

        // --- Step 2: Find the first occurrence of the marker in the text ---
        // We use a case-insensitive search (stripos) for better reliability.
        $markerPosition = stripos($source, $cleanMarker);

        // --- Step 3: Handle the case where the marker is not found ---
        if ($markerPosition === false) {
            // If the marker doesn't exist in the source text, return the original text.
            return $source;
        }

        // --- Step 4: Calculate the length of the text to keep ---
        // This includes all text from the beginning up to the very end of the marker.
        $lengthToKeep = $markerPosition + strlen($cleanMarker);

        // --- Step 5: Extract the desired part of the string and clean it up ---
        $substring = substr($source, 0, $lengthToKeep);

        // Finally, trim the result to remove any unwanted leading/trailing whitespace.
        return trim($substring);
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
