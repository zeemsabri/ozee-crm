<?php

namespace App\Services\StepHandlers;

use App\Models\ExecutionLog;
use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;
use Illuminate\Support\Arr;

class DefineVariableStepHandler implements StepHandlerContract
{
    public function __construct(
        protected WorkflowEngineService $engine
    ) {}

    public function handle(array $context, WorkflowStep $step, ?ExecutionLog $execLog = null): array
    {
        $cfg = $step->step_config ?? [];
        $variables = $cfg['variables'] ?? [];
        $parsed = [];

        foreach ($variables as $var) {
            $name = trim($var['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $value = $var['value'] ?? null;
            $resolvedValue = $this->applyTemplate($value, $context);
            
            $parsed[$name] = $resolvedValue;
        }

        return [
            'parsed' => $parsed,
            'context' => [
                'variables' => $parsed, // Allow accessing as {{variables.var_name}}
            ],
        ];
    }

    protected function applyTemplate($value, array $ctx)
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->applyTemplate($v, $ctx), $value);
        }
        if (! is_string($value)) {
            return $value;
        }

        // Direct variable access: {{some.path}}
        if (preg_match('/^{{\s*([^}]+)\s*}}$/', $value, $matches)) {
            $path = trim($matches[1]);
            return $this->getFromContextPath($ctx, $path);
        }

        // String interpolation
        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($m) use ($ctx) {
            $path = trim($m[1]);
            $val = $this->getFromContextPath($ctx, $path);
            return is_scalar($val) ? (string) $val : json_encode($val);
        }, $value);
    }

    protected function getFromContextPath(array $context, string $path)
    {
        $value = Arr::get($context, $path);
        if ($value !== null) {
            return $value;
        }

        if (str_starts_with($path, 'step_')) {
            $parts = explode('.', $path, 2);
            if (count($parts) > 1) {
                // Try .parsed fallback
                $fallbackPath = $parts[0] . '.parsed.' . $parts[1];
                $value = Arr::get($context, $fallbackPath);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }
}
