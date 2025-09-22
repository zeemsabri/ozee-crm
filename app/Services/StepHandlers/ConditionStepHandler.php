<?php

namespace App\Services\StepHandlers;

use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ConditionStepHandler implements StepHandlerContract
{
    public function __construct(
        protected WorkflowEngineService $engine,
    ) {}

    public function handle(array $context, WorkflowStep $step): array
    {
        $cfg = $step->step_config ?? [];
        $logic = strtoupper($cfg['logic'] ?? 'AND');
        $rules = $cfg['rules'] ?? $step->condition_rules ?? [];
        $result = $this->evaluateRules($rules, $logic, $context);

        $branch = $result ? 'yes_steps' : 'no_steps';
        $children = $step->$branch ?? [];

        if (!is_array($children) || count($children) === 0) {
            $all = $step->workflow->steps()->orderBy('step_order')->get();
            $children = [];
            foreach ($all as $cand) {
                $cfgCand = $cand->step_config ?? [];
                if (($cfgCand['_parent_id'] ?? null) == $step->id) {
                    $b = strtolower((string)($cfgCand['_branch'] ?? 'yes'));
                    if (($result && $b === 'yes') || (!$result && $b === 'no')) {
                        $children[] = $cand;
                    }
                }
            }
        }

        if (is_array($children) && count($children) > 0) {
            $this->engine->executeSteps($children, $step->workflow, $context);
        }

        return [
            'parsed' => [
                'condition' => $result ? 'YES' : 'NO',
            ],
            'context' => [
                'condition' => [
                    (string) $step->id => $result,
                ],
            ],
        ];
    }

    protected function evaluateRules(array $rules, string $logic, array $context): bool
    {
        if (empty($rules)) {
            return true;
        }

        $logic = strtoupper($logic);
        $results = [];

        foreach ($rules as $rule) {
            if (!isset($rule['left']) && isset($rule['field'])) {
                $rule['left'] = ['type' => 'var', 'path' => $rule['field']];
            }
            if (!isset($rule['right']) && array_key_exists('value', $rule)) {
                $rule['right'] = ['type' => 'literal', 'value' => $rule['value']];
            }

            $op = $rule['operator'] ?? ($rule['op'] ?? '==');
            $leftVal = $this->resolveSide($rule['left'] ?? ['type' => 'literal', 'value' => null], $context);
            $rightVal = $this->resolveSide($rule['right'] ?? ['type' => 'literal', 'value' => null], $context);

            // THE CRITICAL FIX: Explicitly check for null when a value is expected.
            // This prevents incorrect evaluations when a relationship path does not exist.
            if ($leftVal === null && $rightVal !== null && $op !== '!=' && $op !== '<>') {
                $results[] = false;
                continue;
            }

            $results[] = $this->compareAny($leftVal, $op, $rightVal);
        }

        return $logic === 'OR' ? Arr::hasAny($results, true) : !in_array(false, $results, true);
    }

    protected function resolveSide(array $side, array $ctx)
    {
        $type = strtolower((string)($side['type'] ?? 'literal'));
        if ($type === 'var') {
            $path = (string)($side['path'] ?? '');
            if (preg_match('/^{{\s*([^}]+)\s*}}$/', $path, $matches)) {
                $path = trim($matches[1]);
            }
            return Arr::get($ctx, $path);
        }
        $val = $side['value'] ?? null;
        return $this->applyTemplate($val, $ctx);
    }

    protected function applyTemplate($value, array $ctx)
    {
        if (is_array($value)) {
            return array_map(fn($v) => $this->applyTemplate($v, $ctx), $value);
        }
        if (!is_string($value)) {
            return $value;
        }
        if (preg_match('/^{{\s*([^}]+)\s*}}$/', $value, $matches)) {
            $path = trim($matches[1]);
            return Arr::get($ctx, $path);
        }
        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($m) use ($ctx) {
            $path = trim($m[1]);
            $val = Arr::get($ctx, $path);
            return is_scalar($val) ? (string) $val : json_encode($val);
        }, $value);
    }


    protected function compareAny($left, string $operator, $right): bool
    {
        $op = strtolower(trim($operator));
        if (in_array($op, ['>', '>=', '<', '<=', '> =', '< ='])) {
            if (is_numeric($left) && is_numeric($right)) {
                $left = $left + 0;
                $right = $right + 0;
            }
        }

        $asCarbon = function ($value) {
            if ($value instanceof CarbonInterface) return $value;
            if ($value instanceof DateTimeInterface) return Carbon::instance($value);
            if (is_numeric($value)) return Carbon::createFromTimestamp((int)$value);
            if (is_string($value) && trim($value) !== '') {
                try {
                    return Carbon::parse($value);
                } catch (\Throwable $e) {
                    return null;
                }
            }
            return null;
        };

        return match ($op) {
            '==', '=' => $this->looseEq($left, $right),
            '!=', '<>' => !$this->looseEq($left, $right),
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            'in' => $this->inArray($left, $right),
            'not in', 'not_in' => !$this->inArray($left, $right),
            'contains' => $this->contains($left, $right),
            'empty' => empty($left),
            'not_empty' => !empty($left),
            'truthy' => (bool)$left || (is_string($left) && $left !== '') || (is_numeric($left) && $left != 0),
            'today' => (function () use ($asCarbon, $left) {
                $dt = $asCarbon($left);
                if (!$dt) return false;
                $now = Carbon::now($dt->getTimezone());
                return $dt->isSameDay($now);
            })(),
            'in_past' => (function () use ($asCarbon, $left) {
                $dt = $asCarbon($left);
                if (!$dt) return false;
                return $dt->lt(Carbon::now($dt->getTimezone()));
            })(),
            'in_future' => (function () use ($asCarbon, $left) {
                $dt = $asCarbon($left);
                if (!$dt) return false;
                return $dt->gt(Carbon::now($dt->getTimezone()));
            })(),
            default => false,
        };
    }

    protected function looseEq($a, $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return json_encode($a) === json_encode($b);
        }

        if (is_bool($a)) {
            $b_str = strtolower(trim((string)$b));
            if ($b_str === 'true') return $a === true;
            if ($b_str === 'false') return $a === false;
        }

        return (string)$a === (string)$b;
    }

    protected function inArray($needle, $haystack): bool
    {
        if (!is_array($haystack)) {
            if (is_string($haystack)) {
                $haystack = array_values(array_filter(array_map('trim', explode(',', $haystack)), fn($x) => $x !== ''));
            } else {
                return false;
            }
        }
        return in_array($needle, $haystack, true);
    }

    protected function contains($container, $item): bool
    {
        if (is_string($container)) {
            return is_string($item) ? Str::contains($container, $item) : false;
        }
        if (is_array($container)) {
            return in_array($item, $container, true);
        }
        return false;
    }
}

