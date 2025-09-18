<?php

namespace App\Services\StepHandlers;

use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CreateRecordStepHandler implements StepHandlerContract
{
    public function handle(array $context, WorkflowStep $step): array
    {

        $cfg = $step->step_config ?? [];
        $modelName = $cfg['target_model'] ?? null; // e.g., Lead
        $fields = $cfg['fields'] ?? [];
        if (!$modelName) {
            throw new \InvalidArgumentException('target_model is required for CREATE_RECORD');
        }
        $class = $this->resolveModelClass($modelName);
        if (!$class) {
            throw new \RuntimeException("Model {$modelName} not found");
        }
        /** @var Model $instance */
        $instance = new $class();
        $data = [];
        foreach ($fields as $f) {
            // Support multiple front-end schemas: `column`, `field`, or `name`
            $key = $f['column'] ?? ($f['field'] ?? ($f['name'] ?? null));
            $val = $f['value'] ?? null;
            if (!$key) continue;
            $resolved = $this->applyTemplate($val, $context);
            // Soft validation against allowed values registry (if available)
            try {
                app(\App\Services\ValueSetValidator::class)->validate($modelName, $key, $resolved);
            } catch (\Throwable $e) {
                // If enforcement is enabled, the validator may throw; rethrow to halt execution
                throw $e;
            }
            $data[$key] = $resolved;
        }

        // Merge model defaults for any missing/empty fields when supported
        if (is_subclass_of($class, \App\Contracts\CreatableViaWorkflow::class)) {
            try {
                $modelDefaults = $class::defaultsOnCreate($context) ?? [];
                foreach ($modelDefaults as $k => $v) {
                    $needsDefault = !array_key_exists($k, $data) || $data[$k] === null || $data[$k] === '';
                    if ($needsDefault) {
                        // Validate against allowed values; ValueSetValidator only throws when enforce_validation=true
                        app(\App\Services\ValueSetValidator::class)->validate($modelName, $k, $v);
                        $data[$k] = $v;
                    }
                }
            } catch (\Throwable $e) {
                // Swallow defaults errors except when validator/enforcement throws (already thrown)
                Optionally: Log::warning('defaultsOnCreate failed: ' . $e->getMessage());
            }
        }

        // Guard: prevent blank inserts when no fields provided or nothing fillable
        if (empty($data)) {
            throw new \InvalidArgumentException("No fields provided for CREATE_RECORD on model {$modelName}.");
        }

        $instance->fill($data);
        // After fill, ensure we actually have attributes to save
        $dirty = method_exists($instance, 'getDirty') ? $instance->getDirty() : $data;
        if (empty($dirty)) {
            throw new \InvalidArgumentException("No valid/fillable fields set for CREATE_RECORD on model {$modelName}. Check field mappings.");
        }

        // Avoid feedback loop: save without firing Eloquent model events
        Model::withoutEvents(function () use ($instance) {
            $instance->save();
        });

        return [
            'parsed' => [
                'id' => $instance->getKey(),
                'model' => $class,
            ],
            'context' => [
                strtolower(class_basename($class)) => $instance->toArray(),
            ],
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

    protected function applyTemplate($value, array $ctx)
    {
        if (!is_string($value)) return $value;
        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($m) use ($ctx) {
            $path = trim($m[1]);
            $parts = preg_split('/\.|\:/', $path);
            $val = $ctx;
            foreach ($parts as $p) {
                if (is_array($val) && array_key_exists($p, $val)) {} else {
                    return '';
                }
            }
            return is_scalar($val) ? (string) $val : json_encode($val);
        }, $value);
    }
}
