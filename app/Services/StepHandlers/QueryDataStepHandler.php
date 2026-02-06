<?php

namespace App\Services\StepHandlers;

use App\Models\ExecutionLog;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class QueryDataStepHandler implements StepHandlerContract
{
    public function handle(array $context, WorkflowStep $step, ?ExecutionLog $execLog = null): array
    {
        $cfg = $step->step_config ?? [];
        $modelName = $cfg['model'] ?? ($cfg['target_model'] ?? null);
        if (! $modelName) {
            throw new \InvalidArgumentException('model is required for QUERY_DATA');
        }
        $class = $this->resolveModelClass($modelName);
        if (! $class) {
            throw new \RuntimeException("Model {$modelName} not found");
        }

        /** @var \Illuminate\Database\Eloquent\Builder $q */
        $q = $class::query();

        // Eager-load relationships if requested
        $with = [];
        // Simple array of relation paths, e.g., ["user.projects", "email.conversation"]
        if (! empty($cfg['with']) && is_array($cfg['with'])) {
            $with = array_values(array_filter(array_map('strval', $cfg['with']), fn ($p) => $p !== ''));
        }
        // Relationships payload from RelatedDataPicker (roots + nested)
        // Relationships payload from RelatedDataPicker (roots + nested)
        if (empty($with) && ! empty($cfg['relationships']) && is_array($cfg['relationships'])) {
            $rels = $cfg['relationships'];

            // Scenario 1: Complex structure with 'roots', 'nested', and potentially 'fields'
            if (isset($rels['roots']) && is_array($rels['roots'])) {
                $roots = $rels['roots'];
                $fieldsCfg = is_array($rels['fields'] ?? null) ? $rels['fields'] : [];

                foreach ($roots as $root) {
                    $rootName = (string) $root;

                    // Apply field filtering if defined (New Scenario), otherwise load all (Existing Scenario)
                    if (! empty($fieldsCfg[$rootName]) && is_array($fieldsCfg[$rootName])) {
                        $with[] = $rootName.':'.implode(',', $fieldsCfg[$rootName]);
                    } else {
                        $with[] = $rootName;
                    }

                    // Handle nested relationships (consistent across scenarios)
                    $nested = is_array(($rels['nested'] ?? [])[$rootName] ?? null) ? ($rels['nested'][$rootName] ?? []) : [];
                    foreach ($nested as $child) {
                        $with[] = $rootName.'.'.$child;
                    }
                }
            }
            // Scenario 2: Simple flat array structure (Fallback for legacy or manual configs)
            else {
                foreach ($rels as $key => $val) {
                    $relItem = is_int($key) ? $val : $key;
                    if (is_string($relItem) && $relItem !== '') {
                        $with[] = $relItem;
                    }
                }
            }

            // De-duplicate and filter empty values
            $with = array_values(array_unique(array_filter($with)));
        }
        if (! empty($with)) {
            $q->with($with);
        }

        $conditions = $cfg['conditions'] ?? [];
        foreach ($conditions as $cond) {
            $field = $cond['field'] ?? ($cond['column'] ?? null);
            $op = $cond['op'] ?? ($cond['operator'] ?? '=');
            $val = $cond['value'] ?? null;
            if (! $field) {
                continue;
            }
            [$op, $val, $method] = $this->normalizeOperatorAndValue($op, $val, $context);

            if ($method === 'whereIn') {
                $q->whereIn($field, $val);
            } elseif ($method === 'whereNotIn') {
                $q->whereNotIn($field, $val);
            } elseif ($method === 'whereNull') {
                $q->whereNull($field);
            } elseif ($method === 'whereNotNull') {
                $q->whereNotNull($field);
            } else {
                $q->where($field, $op, $val);
            }
        }

        // Order & Limit
        if (! empty($cfg['order'])) {
            $order = $cfg['order']; // e.g., [['field'=>'created_at','dir'=>'desc']]
            foreach ($order as $o) {
                $f = $o['field'] ?? null;
                $d = strtolower($o['dir'] ?? 'asc');
                if ($f) {
                    $q->orderBy($f, in_array($d, ['asc', 'desc']) ? $d : 'asc');
                }
            }
        }

        $single = (bool) ($cfg['single'] ?? false) || (isset($cfg['mode']) && strtolower((string) $cfg['mode']) === 'single');
        $countOnly = (bool) ($cfg['count_only'] ?? false);

        // Logging preview (avoid mutating builder before execution)
        if ($single) {
            $record = $q->first();
            $count = $record ? 1 : 0;
            $recordArr = $record instanceof Model ? $record->toArray() : ($record ? (array) $record : null);
            $parsed = [
                'count' => $count,
                'record' => $recordArr,
                // keep records for compatibility (single-item array)
                'records' => $recordArr ? [$recordArr] : [],
            ];
        } else {
            // Respect limit for multi fetch, with sane bounds
            $limit = (int) ($cfg['limit'] ?? 50);
            if ($limit <= 0 || $limit > 1000) {
                $limit = 50;
            }
            $records = $q->limit($limit)->get();
            $count = $countOnly ? (clone $q)->count() : $records->count();
            $recordsArr = $countOnly ? [] : $records->map(function ($m) {
                if ($m instanceof Model) {
                    return $m->toArray();
                }

                return (array) $m;
            })->values()->all();
            $parsed = [
                'count' => $count,
                'records' => $recordsArr,
            ];
        }

        $outputKey = $cfg['output_key'] ?? null;
        $contextOut = [];
        if (is_string($outputKey) && $outputKey !== '') {
            $contextOut[$outputKey] = $parsed;
        }

        return [
            'parsed' => $parsed,
            'context' => $contextOut,
        ];
    }

    protected function resolveModelClass(string $name): ?string
    {
        $candidates = [
            $name,
            'App\\Models\\'.$name,
        ];
        foreach ($candidates as $c) {
            if (class_exists($c)) {
                return $c;
            }
        }

        return null;
    }

    protected function normalizeOperatorAndValue(string $op, $val, array $ctx): array
    {
        $op = strtolower(trim($op));
        // Interpolate templates for scalar or string values
        $val = $this->applyTemplate($val, $ctx);

        return match ($op) {
            'in' => ['in', is_array($val) ? $val : $this->csvToArray((string) $val), 'whereIn'],
            'not in', 'not_in' => ['not in', is_array($val) ? $val : $this->csvToArray((string) $val), 'whereNotIn'],
            '!=' => ['!=', $val, 'where'],
            '>=' => ['>=', $val, 'where'],
            '<=' => ['<=', $val, 'where'],
            '>' => ['>', $val, 'where'],
            '<' => ['<', $val, 'where'],
            'is null', 'is_null', 'null' => ['=', null, 'whereNull'],
            'is not null', 'is_not_null', 'not null', 'not_null' => ['!=', null, 'whereNotNull'],
            'older_than_hours' => ['<', now()->subHours((int) $val), 'where'],
            'within_hours' => ['>=', now()->subHours((int) $val), 'where'],
            'older_than_days' => ['<', now()->subDays((int) $val), 'where'],
            'within_days' => ['>=', now()->subDays((int) $val), 'where'],
            default => ['=', $val, 'where'],
        };
    }

    protected function applyTemplate($value, array $ctx)
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->applyTemplate($v, $ctx), $value);
        }
        if ($value === 'true') {
            return 1;
        }
        if ($value === 'false') {
            return 0;
        }
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($m) use ($ctx) {
            $path = trim($m[1]);
            $parts = preg_split('/\.|\:/', $path);
            $val = $ctx;
            foreach ($parts as $p) {
                if (is_array($val) && array_key_exists($p, $val)) {
                    $val = $val[$p];
                } else {
                    return '';
                }
            }

            return is_scalar($val) ? (string) $val : json_encode($val);
        }, $value);
    }

    protected function csvToArray(string $s): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $s)), fn ($x) => $x !== ''));
    }
}
