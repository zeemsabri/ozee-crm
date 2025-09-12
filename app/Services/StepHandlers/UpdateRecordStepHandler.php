<?php

namespace App\Services\StepHandlers;

use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Model;

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
            $key = $f['field'] ?? null;
            $val = $f['value'] ?? null;
            if (!$key) continue;
            $data[$key] = $this->applyTemplate($val, $context);
        }
        $model->fill($data);
        // Avoid feedback loop: flag this instance so global subscriber ignores this event
        $model->__automation_suppressed = true;
        $model->save();

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
