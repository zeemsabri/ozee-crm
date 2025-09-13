<?php

namespace App\Services\StepHandlers;

use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;

class ConditionStepHandler implements StepHandlerContract
{
    public function __construct(
        protected WorkflowEngineService $engine,
    ) {}

    public function handle(array $context, WorkflowStep $step): array
    {
        // Determine evaluation mode: new shape (step_config.rules + logic) or legacy (condition_rules)
        $cfg = $step->step_config ?? [];
        $logic = strtoupper($cfg['logic'] ?? 'AND');
        $newRules = $cfg['rules'] ?? null;
        $legacyRules = $step->condition_rules ?? [];

        if (is_array($newRules) && count($newRules) > 0) {
            $result = $this->evaluateNewRules($newRules, $logic, $context);
        } else {
            $result = $this->evaluateLegacyRules($legacyRules, $logic, $context);
        }

        // Determine branch
        $branch = $result ? 'yes_steps' : 'no_steps';
        $children = $step->$branch ?? [];

        // If not nested, try to discover children from flat steps using _parent_id/_branch markers
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

        // Execute selected branch
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

    /**
     * Legacy: [{ field, operator, value }], implicit AND unless $logic='OR'
     */
    protected function evaluateLegacyRules(array $rules, string $logic, array $context): bool
    {
        if (!is_array($rules) || count($rules) === 0) {
            return true; // no rules means YES
        }
        $logic = strtoupper($logic);
        $results = [];
        foreach ($rules as $rule) {
            $field = $rule['field'] ?? null;
            $operator = $rule['operator'] ?? ($rule['op'] ?? '==');
            if (!$field) { $results[] = false; continue; }
            $left = $this->getFromContext($context, $field);
            $right = $this->applyTemplate($rule['value'] ?? null, $context);
            $results[] = $this->compareAny($left, $operator, $right);
        }
        return $logic === 'OR' ? in_array(true, $results, true) : !in_array(false, $results, true);
    }

    /**
     * New shape: rules: [{ left:{type:'var'|'literal',path|value}, operator, right:{...} }], with group logic.
     */
    protected function evaluateNewRules(array $rules, string $logic, array $context): bool
    {
        $logic = strtoupper($logic);
        $results = [];
        foreach ($rules as $r) {
            // Backward compatibility fallback if provided in field/value keys
            if (!isset($r['left']) && isset($r['field'])) {
                $r['left'] = ['type' => 'var', 'path' => $r['field']];
            }
            if (!isset($r['right']) && array_key_exists('value', $r)) {
                $r['right'] = ['type' => 'literal', 'value' => $r['value']];
            }
            $op = $r['operator'] ?? ($r['op'] ?? '==');
            $leftVal = $this->resolveSide($r['left'] ?? ['type' => 'literal', 'value' => null], $context);
            $rightVal = $this->resolveSide($r['right'] ?? ['type' => 'literal', 'value' => null], $context);
            $results[] = $this->compareAny($leftVal, $op, $rightVal);
        }
        return $logic === 'OR' ? in_array(true, $results, true) : !in_array(false, $results, true);
    }

    protected function resolveSide(array $side, array $ctx)
    {
        $type = strtolower((string)($side['type'] ?? 'literal'));
        if ($type === 'var') {
            $path = (string)($side['path'] ?? '');
            return $this->getFromContext($ctx, $path);
        }
        // literal with template interpolation support
        $val = $side['value'] ?? null;
        return $this->applyTemplate($val, $ctx);
    }

    protected function getFromContext(array $context, string $path)
    {
        if ($path === '') return null;
        $parts = preg_split('/\.|\:/', $path);
        $val = $context;
        foreach ($parts as $p) {
            if (is_array($val) && array_key_exists($p, $val)) {
                $val = $val[$p];
            } else {
                return null;
            }
        }
        return $val;
    }

    protected function applyTemplate($value, array $ctx)
    {
        if (is_array($value)) {
            return array_map(fn($v) => $this->applyTemplate($v, $ctx), $value);
        }
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

    protected function compareAny($left, string $operator, $right): bool
    {
        $op = strtolower(trim($operator));
        // Numeric coercion for numeric comparisons
        if (in_array($op, ['>','>=','<','<=','> =','< ='])) {
            if (is_numeric($left) && is_numeric($right)) {
                $left = $left + 0; $right = $right + 0;
            }
        }
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
            'truthy' => (bool)$left === true || (is_string($left) && $left !== '') || (is_numeric($left) && $left != 0),
            default => false,
        };
    }

    protected function looseEq($a, $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return json_encode($a) === json_encode($b);
        }
        return (string)$a === (string)$b;
    }

    protected function inArray($needle, $haystack): bool
    {
        if (!is_array($haystack)) {
            // allow CSV string
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
            return is_string($item) ? str_contains($container, $item) : false;
        }
        if (is_array($container)) {
            return in_array($item, $container, true);
        }
        return false;
    }
}
