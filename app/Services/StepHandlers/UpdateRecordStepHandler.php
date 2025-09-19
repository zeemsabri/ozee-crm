<?php

namespace App\Services\StepHandlers;

use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class UpdateRecordStepHandler implements StepHandlerContract
{
    public function handle(array $context, WorkflowStep $step): array
    {
        $cfg = $step->step_config ?? [];
        $modelName = $cfg['target_model'] ?? null;
        $recordId = $cfg['record_id'] ?? null;
        $fields = $cfg['fields'] ?? [];
        if (!$modelName || !$recordId) {
            throw new \InvalidArgumentException('target_model and record_id are required for UPDATE_RECORD');
        }
        $class = $this->resolveModelClass($modelName);
        if (!$class) {
            throw new \RuntimeException("Model {$modelName} not found");
        }
        $id = $this->applyTemplate((string)$recordId, $context);
        /** @var Model|null $model */
        $model = $class::query()->find($id);
        if (!$model) {
            throw new \RuntimeException("Record {$id} not found for model {$class}");
        }
        $data = [];
        foreach ($fields as $f) {
            // Support multiple front-end schemas: `column`, `field`, or `name`
            $key = $f['column'] ?? ($f['field'] ?? ($f['name'] ?? null));
            $val = $f['value'] ?? null;
            if (!$key) continue;
            $resolved = $this->applyTemplate($val, $context);
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
        $candidates[] = 'App\\Models\\' . $base;
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

    protected function applyTemplate($value, array $ctx)
    {
        // Match WorkflowEngineService::getTemplatedValue semantics
        if (is_array($value)) {
            return array_map(fn($v) => $this->applyTemplate($v, $ctx), $value);
        }
        if (!is_string($value)) return $value;

        // If string is exactly one token, return the raw value (could be array/object)
        if (preg_match('/^\s*{{\s*([^}]+)\s*}}\s*$/', $value, $m)) {
            $path = trim($m[1]);
            $val = $this->getFromContextPath($ctx, $path);
            return $val === null ? '' : $val;
        }

        // Otherwise interpolate
        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($m) use ($ctx) {
            $path = trim($m[1]);
            $val = $this->getFromContextPath($ctx, $path);
            if (is_scalar($val) || $val === null) return (string) $val;
            return json_encode($val);
        }, $value);
    }

    protected function getFromContextPath(array $ctx, string $path)
    {
        if ($path === '') return null;
        $parts = preg_split('/\.|\:/', $path);
        $val = $ctx;
        foreach ($parts as $p) {
            if (is_array($val) && array_key_exists($p, $val)) {
                $val = $val[$p];
            } else {
                return null;
            }
        }
        return $val;
    }

    protected function normalizeMorphType(string $key, $value)
    {
        if (!is_string($value)) return $value;
        $isTypeColumn = str_ends_with($key, '_type') || str_ends_with($key, 'able_type') || $key === 'type';
        if (!$isTypeColumn) return $value;

        // If it's already a class, keep it
        if (class_exists($value)) return $value;

        // Try Laravel morph map
        $mapped = Relation::getMorphedModel($value) ?: Relation::getMorphedModel(strtolower($value));
        if ($mapped && class_exists($mapped)) return $mapped;

        // Try App\Models\Studly
        $candidate = 'App\\Models\\' . Str::studly($value);
        if (class_exists($candidate)) return $candidate;

        return $value;
    }
}
