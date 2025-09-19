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
            // Fallback: resolve from step_config.promptRef.id (front-end stores link here)
            $cfg = $step->step_config ?? [];
            $promptId = is_array($cfg) ? ($cfg['promptRef']['id'] ?? null) : null;
            if ($promptId) {
                $prompt = Prompt::find($promptId);
            }
        }
        if (!$prompt) {
            throw new \RuntimeException('Prompt not found for AI_PROMPT step');
        }

        // Optionally enrich context with campaign data
        $cfg = $step->step_config ?? [];
        if (!empty($cfg['campaign_id']) && class_exists(\App\Models\Campaign::class)) {
            $campaign = \App\Models\Campaign::find($cfg['campaign_id']);
            if ($campaign) {
                $context = array_replace_recursive($context, [
                    'campaign' => $campaign->toArray(),
                ]);
            }
        }

        // NEW: Eager-load selected relationships based on base model context (Trigger or Loop Item)
        $withTree = [];
        $rels = $cfg['relationships'] ?? null;
        if (is_array($rels)) {
            $baseModel = $rels['base_model'] ?? null;
            $roots = is_array($rels['roots'] ?? null) ? $rels['roots'] : [];
            $nested = is_array($rels['nested'] ?? null) ? $rels['nested'] : [];
            $fields = is_array($rels['fields'] ?? null) ? $rels['fields'] : [];

            if ($baseModel) {
                $class = $this->resolveModelClass($baseModel);
                if ($class) {
                    // Determine the current record id
                    $id = null;
                    if (isset($context['loop']['item']['id'])) {
                        $id = $context['loop']['item']['id'];
                    }
                    if (!$id) {
                        $key = strtolower($baseModel);
                        $id = $context['trigger'][$key]['id'] ?? null;
                    }

                    if ($id) {
                        // Build with paths
                        $paths = [];
                        foreach ($roots as $r) { $paths[] = $r; }
                        foreach ($nested as $rootName => $children) {
                            foreach ((array)$children as $c) { $paths[] = $rootName . '.' . $c; }
                        }
                        $paths = array_values(array_unique(array_filter($paths)));

                        try {
                            /** @var \Illuminate\Database\Eloquent\Model|null $record */
                            $record = $class::query()->with($paths)->find($id);
                            if ($record) {
                                $asArr = $record->toArray();
                                // Build `with` structure honoring field selections
                                // Include roots explicitly (even if no nested children)
                                foreach ($roots as $r) {
                                    $val = $this->getFromArrayByPath($asArr, $r);
                                    $sel = $fields[$r] ?? ['*'];
                                    $filtered = $this->filterValueByFields($val, $sel);
                                    $this->setArrayByPath($withTree, $r, $filtered);
                                }
                                // Include nested
                                foreach ($nested as $rootName => $children) {
                                    foreach ((array)$children as $c) {
                                        $full = $rootName . '.' . $c;
                                        $val = $this->getFromArrayByPath($asArr, $full);
                                        $sel = $fields[$full] ?? ['*'];
                                        $filtered = $this->filterValueByFields($val, $sel);
                                        $this->setArrayByPath($withTree, $full, $filtered);
                                    }
                                }
                                // Merge into context for the AI template
                                $context = array_replace_recursive($context, ['with' => $withTree]);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('AiPromptStepHandler.relations_load_failed', [
                                'base_model' => $baseModel,
                                'id' => $id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
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

    protected function setArrayByPath(array &$target, string $path, $value): void
    {
        $parts = explode('.', $path);
        $ref =& $target;
        foreach ($parts as $i => $seg) {
            if ($i === count($parts) - 1) {
                $ref[$seg] = $value;
                return;
            }
            if (!isset($ref[$seg]) || !is_array($ref[$seg])) {
                $ref[$seg] = [];
            }
            $ref =& $ref[$seg];
        }
    }

    protected function filterValueByFields($value, array $selected)
    {
        // '*' means select all
        if (in_array('*', $selected, true)) return $value;
        // Normalize selection keys
        $keys = array_values(array_filter(array_map('strval', $selected), fn($k) => $k !== ''));
        if (empty($keys)) return $value; // nothing selected => keep as-is for safety
        if (is_array($value)) {
            // Determine if list of rows or assoc
            $isList = array_keys($value) === range(0, count($value) - 1);
            if ($isList) {
                return array_map(function ($row) use ($keys) {
                    return is_array($row) ? array_intersect_key($row, array_fill_keys($keys, true)) : $row;
                }, $value);
            }
            // associative array
            return array_intersect_key($value, array_fill_keys($keys, true));
        }
        return $value;
    }

    protected function resolveModelClass(string $name): ?string
    {
        $candidates = [
            $name,
            'App\\Models\\' . $name,
        ];
        foreach ($candidates as $c) {
            if (class_exists($c)) return $c;
        }
        return null;
    }
}
