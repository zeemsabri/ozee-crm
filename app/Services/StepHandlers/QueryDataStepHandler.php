<?php

namespace App\Services\StepHandlers;

use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class QueryDataStepHandler implements StepHandlerContract
{
    public function handle(array $context, WorkflowStep $step): array
    {
        $cfg = $step->step_config ?? [];
        $modelName = $cfg['model'] ?? ($cfg['target_model'] ?? null);
        if (!$modelName) {
            throw new \InvalidArgumentException('model is required for QUERY_DATA');
        }
        $class = $this->resolveModelClass($modelName);
        if (!$class) {
            throw new \RuntimeException("Model {$modelName} not found");
        }

        /** @var \Illuminate\Database\Eloquent\Builder $q */
        $q = $class::query();

        $conditions = $cfg['conditions'] ?? [];
        foreach ($conditions as $cond) {
            // QueryDataStepHandler.php: Line 26 (Updated)
            $field = $cond['field'] ?? ($cond['column'] ?? null);
            $op = $cond['op'] ?? ($cond['operator'] ?? '=');
            $val = $cond['value'] ?? null;
            if (!$field) continue;
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
        if (!empty($cfg['order'])) {
            $order = $cfg['order']; // e.g., [['field'=>'created_at','dir'=>'desc']]
            foreach ($order as $o) {
                $f = $o['field'] ?? null; $d = strtolower($o['dir'] ?? 'asc');
                if ($f) { $q->orderBy($f, in_array($d, ['asc','desc']) ? $d : 'asc'); }
            }
        }
        $limit = (int)($cfg['limit'] ?? 50);
        if ($limit <= 0 || $limit > 1000) { $limit = 50; }


// Log the query
        Log::info('Executing query', [
            'sql' => $q->limit($limit)->toSql(),
            'bindings' => $q->getBindings()
        ]);

        // Execute
        $records = $q->limit($limit)->get();
        $countOnly = (bool)($cfg['count_only'] ?? false);
        $count = $countOnly ? (clone $q)->count() : $records->count();

        $recordsArr = $countOnly ? [] : $records->map(function ($m) {
            if ($m instanceof Model) {
                return $m->toArray();
            }
            return (array)$m;
        })->values()->all();

        $parsed = [
            'count' => $count,
            'records' => $recordsArr,
        ];

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
            'App\\Models\\' . $name,
        ];
        foreach ($candidates as $c) {
            if (class_exists($c)) return $c;
        }
        return null;
    }

    protected function normalizeOperatorAndValue(string $op, $val, array $ctx): array
    {
        $op = strtolower(trim($op));
        // Interpolate templates for scalar or string values
        $val = $this->applyTemplate($val, $ctx);

        return match ($op) {
            'in' => ['in', is_array($val) ? $val : $this->csvToArray((string)$val), 'whereIn'],
            'not in', 'not_in' => ['not in', is_array($val) ? $val : $this->csvToArray((string)$val), 'whereNotIn'],
            '!=' => ['!=', $val, 'where'],
            '>=' => ['>=', $val, 'where'],
            '<=' => ['<=', $val, 'where'],
            '>' => ['>', $val, 'where'],
            '<' => ['<', $val, 'where'],
            'is null', 'null' => ['=', null, 'whereNull'],
            'is not null', 'not null', 'not_null' => ['!=', null, 'whereNotNull'],
            default => ['=', $val, 'where'],
        };
    }

    protected function applyTemplate($value, array $ctx)
    {
        if (is_array($value)) {
            return array_map(fn($v) => $this->applyTemplate($v, $ctx), $value);
        }
        if($value === "true") return 1;
        if($value === "false") return 0;
        if (!is_string($value)) return $value;
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
        return array_values(array_filter(array_map('trim', explode(',', $s)), fn($x) => $x !== ''));
    }
}
