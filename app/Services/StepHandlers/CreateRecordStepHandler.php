<?php

namespace App\Services\StepHandlers;

use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Model;

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
            $key = $f['field'] ?? null;
            $val = $f['value'] ?? null;
            if (!$key) continue;
            $data[$key] = $this->applyTemplate($val, $context);
        }

        // Avoid feedback loop: flag this instance so global subscriber ignores this event
        $instance->fill($data);
        $instance->__automation_suppressed = true;
        $instance->save();

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
                if (is_array($val) && array_key_exists($p, $val)) {
                    $val = $val[$p];
                } else {
                    return '';
                }
            }
            return is_scalar($val) ? (string) $val : json_encode($val);
        }, $value);
    }
}
