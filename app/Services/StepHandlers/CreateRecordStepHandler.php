<?php

namespace App\Services\StepHandlers;

use App\Models\ExecutionLog;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Services\WorkflowEngineService;

class CreateRecordStepHandler implements StepHandlerContract
{
    public function __construct(
        protected ?WorkflowEngineService $engine = null
    ) {}
    public function handle(array $context, WorkflowStep $step, ?ExecutionLog $execLog = null): array
    {
        $cfg = $step->step_config ?? [];
        $modelName = $cfg['target_model'] ?? null;
        $fields = $cfg['fields'] ?? [];
        if (! $modelName) {
            throw new \InvalidArgumentException('target_model is required for CREATE_RECORD');
        }
        $class = $this->resolveModelClass($modelName);
        if (! $class) {
            throw new \RuntimeException("Model {$modelName} not found");
        }
        /** @var Model $instance */
        $instance = new $class;
        $data = [];
        foreach ($fields as $f) {
            $key = $f['column'] ?? ($f['field'] ?? ($f['name'] ?? null));
            $val = $f['value'] ?? null;
            if (! $key) {
                continue;
            }

            $resolved = $this->engine 
                ? $this->engine->getTemplatedValue($val, $context)
                : $this->applyTemplate($val, $context);

            if (is_array($resolved) || is_object($resolved)) {
                $resolved = json_encode($resolved);
            }

            // Process special functions like NOW(), CURRENT_TIMESTAMP, etc.
            $resolved = $this->processFunctions($resolved);

            $resolved = $this->normalizeMorphType($key, $resolved);

            try {
                app(\App\Services\ValueSetValidator::class)->validate($modelName, $key, $resolved);
            } catch (\Throwable $e) {
                throw $e;
            }
            $data[$key] = $resolved;
        }

        if (is_subclass_of($class, \App\Contracts\CreatableViaWorkflow::class)) {
            try {
                $modelDefaults = $class::defaultsOnCreate($context) ?? [];
                foreach ($modelDefaults as $k => $v) {
                    $needsDefault = ! array_key_exists($k, $data) || $data[$k] === null || $data[$k] === '';
                    if ($needsDefault) {
                        app(\App\Services\ValueSetValidator::class)->validate($modelName, $k, $v);
                        $data[$k] = $v;
                    }
                }
            } catch (\Throwable $e) {
                // optional logging
            }
        }

        if (empty($data)) {
            throw new \InvalidArgumentException("No fields provided for CREATE_RECORD on model {$modelName}.");
        }

        $instance->fill($data);
        $dirty = method_exists($instance, 'getDirty') ? $instance->getDirty() : $data;
        if (empty($dirty)) {
            throw new \InvalidArgumentException("No valid/fillable fields set for CREATE_RECORD on model {$modelName}. Check field mappings.");
        }

        $instance->save();

        return [
            'parsed' => [
                'id' => $instance->getKey(),
                'new_record_id' => $instance->getKey(),
                'model' => $class,
                'schema' => [
                    'new_record_id' => 'ID',
                    'id' => 'ID',
                ],
            ],
            'context' => [
                strtolower(class_basename($class)) => $instance->toArray(),
            ],
        ];
    }

    protected function resolveModelClass(string $name): ?string
    {
        $base = class_basename($name);
        $candidates = ['App\\Models\\'.$base];
        if (str_contains($name, '\\')) {
            $candidates[] = $name;
        }
        $candidates[] = $base;

        foreach ($candidates as $c) {
            if (class_exists($c) && is_subclass_of($c, Model::class)) {
                return $c;
            }
        }

        return null;
    }

    protected function applyTemplate($value, array $ctx)
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->applyTemplate($v, $ctx), $value);
        }
        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/^\s*{{\s*([^}]+)\s*}}\s*$/', $value, $m)) {
            $path = trim($m[1]);

            return $this->getFromContextPath($ctx, $path);
        }

        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($m) use ($ctx) {
            $path = trim($m[1]);
            $val = $this->getFromContextPath($ctx, $path);

            if (is_scalar($val) || $val === null) {
                return (string) $val;
            }

            return json_encode($val);
        }, $value);
    }

    protected function getFromContextPath(array $context, string $path)
    {
        // 1. Try the direct path first (e.g., "trigger.email.id").
        $value = Arr::get($context, $path);
        if ($value !== null) {
            return $value;
        }

        // 2. If direct path fails, try a ".parsed" fallback for step data (e.g., "step_109.parsed.summary").
        if (str_starts_with($path, 'step_')) {
            $parts = explode('.', $path, 2);
            if (count($parts) > 1) {
                $fallbackPath = $parts[0].'.parsed.'.$parts[1];
                $value = Arr::get($context, $fallbackPath);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        // --- 3. ELOQUENT LOADING FALLBACK ---
        $parts = explode('.', $path);
        $currentValue = $context;
        foreach ($parts as $index => $part) {
            if ($currentValue instanceof Model) {
                try {
                    $currentValue = $currentValue->{$part};
                    continue;
                } catch (\Throwable $e) { return null; }
            }
            if (is_array($currentValue) && array_key_exists($part, $currentValue)) {
                $currentValue = $currentValue[$part];
                continue;
            }
            return null;
        }

        return $currentValue;
    }

    protected function normalizeMorphType(string $key, $value)
    {
        if (! is_string($value)) {
            return $value;
        }
        if (! str_ends_with($key, '_type')) {
            return $value;
        }
        if (class_exists($value)) {
            return $value;
        }
        $mapped = Relation::getMorphedModel($value) ?: Relation::getMorphedModel(strtolower($value));
        if ($mapped && class_exists($mapped)) {
            return $mapped;
        }
        $candidate = 'App\\Models\\'.Str::studly($value);
        if (class_exists($candidate)) {
            return $candidate;
        }

        return $value;
    }

    /**
     * Process special function calls in field values.
     * Supports: NOW(), CURRENT_TIMESTAMP, TODAY(), NULL
     */
    protected function processFunctions($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim(strtoupper($value));

        // Handle NOW() - returns current datetime
        if ($trimmed === 'NOW()' || $trimmed === 'CURRENT_TIMESTAMP' || $trimmed === 'CURRENT_TIMESTAMP()') {
            return now();
        }

        // Handle TODAY() - returns current date at midnight
        if ($trimmed === 'TODAY()') {
            return now()->startOfDay();
        }

        // Handle NULL
        if ($trimmed === 'NULL') {
            return null;
        }

        // Return original value if no function matched
        return $value;
    }
}
