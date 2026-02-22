<?php

namespace App\Services\StepHandlers;

use App\Models\ExecutionLog;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use App\Services\WorkflowEngineService;

class UpdateRecordStepHandler implements StepHandlerContract
{
    public function __construct(
        protected ?WorkflowEngineService $engine = null
    ) {}
    public function handle(array $context, WorkflowStep $step, ?ExecutionLog $execLog = null): array
    {
        $cfg = $step->step_config ?? [];
        $modelName = $cfg['target_model'] ?? null;
        $recordId = $cfg['record_id'] ?? null;
        $fields = $cfg['fields'] ?? [];
        if (! $modelName || ! $recordId) {
            throw new \InvalidArgumentException('target_model and record_id are required for UPDATE_RECORD');
        }
        $class = $this->resolveModelClass($modelName);
        if (! $class) {
            throw new \RuntimeException("Model {$modelName} not found");
        }
        $idValue = $this->engine
            ? $this->engine->getTemplatedValue((string) $recordId, $context)
            : $this->applyTemplate((string) $recordId, $context);
        $id = (string) $idValue;
        /** @var Model|null $model */
        $model = $class::query()->find($id);
        if (! $model) {
            throw new \RuntimeException("Record {$id} not found for model {$class}");
        }
        $data = [];
        foreach ($fields as $f) {
            // Support multiple front-end schemas: `column`, `field`, or `name`
            $key = $f['column'] ?? ($f['field'] ?? ($f['name'] ?? null));
            $val = $f['value'] ?? null;
            if (! $key) {
                continue;
            }
            $resolved = $this->engine 
                ? $this->engine->getTemplatedValue($val, $context)
                : $this->applyTemplate($val, $context);
            // Process special functions like NOW(), CURRENT_TIMESTAMP, etc.
            $resolved = $this->processFunctions($resolved);
            // Normalize morph type aliases like "client" to FQCN when saving *_type columns
            $resolved = $this->normalizeMorphType($key, $resolved);
            // Soft validation against allowed values registry (if available)
            try {
                app(\App\Services\ValueSetValidator::class)->validate($modelName, $key, $resolved);
            } catch (\Throwable $e) {
                // If enforcement is enabled, the validator may throw; rethrow to halt execution
                throw $e;
            }
            $data[$key] = $resolved;
        }
        $model->fill($data);
        // Avoid feedback loop: persist changes without firing Eloquent model events
        Model::withoutEvents(function () use ($model) {
            $model->save();
        });

        return [
            'parsed' => [
                'id' => $model->getKey(),
                'model' => $class,
            ],
            'context' => [
                strtolower(class_basename($class)) => $model->toArray(),
            ],
        ];
    }

    protected function resolveModelClass(string $name): ?string
    {
        $base = class_basename($name);
        $candidates = [];
        // Prefer App\Models first
        $candidates[] = 'App\\Models\\'.$base;
        if (str_contains($name, '\\')) {
            $candidates[] = $name;
        }
        $candidates[] = $base; // last resort
        foreach ($candidates as $c) {
            if (class_exists($c) && is_subclass_of($c, \Illuminate\Database\Eloquent\Model::class)) {
                return $c;
            }
        }

        return null;
    }

    // In both CreateRecordStepHandler.php and UpdateRecordStepHandler.php

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
            return is_scalar($val) || $val === null ? (string)$val : json_encode($val);
        }, $value);
    }

    protected function getFromContextPath(array $context, string $path)
    {
        $value = \Illuminate\Support\Arr::get($context, $path);
        if ($value === null && strpos($path, '.') !== false) {
            $parts = explode('.', $path, 2);
            $value = \Illuminate\Support\Arr::get($context, $parts[0].'.parsed.'.$parts[1]);
        }
        return $value;
    }

    protected function normalizeMorphType(string $key, $value)
    {
        if (! is_string($value)) {
            return $value;
        }
        $isTypeColumn = str_ends_with($key, '_type') || str_ends_with($key, 'able_type') || $key === 'type';
        if (! $isTypeColumn) {
            return $value;
        }

        // If it's already a class, keep it
        if (class_exists($value)) {
            return $value;
        }

        // Try Laravel morph map
        $mapped = Relation::getMorphedModel($value) ?: Relation::getMorphedModel(strtolower($value));
        if ($mapped && class_exists($mapped)) {
            return $mapped;
        }

        // Try App\Models\Studly
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
