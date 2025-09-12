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
        $rules = $step->condition_rules ?? [];

        $result = $this->evaluateRules($rules, $context);
        // Determine branch
        $branch = $result ? 'yes_steps' : 'no_steps';
        $children = $step->$branch ?? [];

        // If not nested, try to discover children from flat steps using _parent_id/_branch markers
        if (!is_array($children) || count($children) === 0) {
            $all = $step->workflow->steps()->orderBy('step_order')->get();
            $children = [];
            foreach ($all as $cand) {
                $cfg = $cand->step_config ?? [];
                if (($cfg['_parent_id'] ?? null) == $step->id) {
                    $b = strtolower((string)($cfg['_branch'] ?? 'yes'));
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

    protected function evaluateRules(array $rules, array $context): bool
    {
        // Support very simple rules: [{ field: 'lead.status', operator: '==', value: 'new' }]
        foreach ($rules as $rule) {
            $field = $rule['field'] ?? null;
            $operator = $rule['operator'] ?? '==';
            $value = $rule['value'] ?? null;
            if (!$field) continue;
            $actual = $this->getFromContext($context, $field);

            $ok = match ($operator) {
                '==', '=' => (string) $actual === (string) $value,
                '!=', '<>' => (string) $actual !== (string) $value,
                '>', '>=' , '<', '<=' => $this->compare($actual, $operator, $value),
                'in' => is_array($value) && in_array($actual, $value, true),
                'not_in' => is_array($value) && !in_array($actual, $value, true),
                'truthy' => (bool) $actual === true || (is_string($actual) && $actual !== '') || (is_numeric($actual) && $actual != 0),
                default => false,
            };

            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    protected function getFromContext(array $context, string $path)
    {
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

    protected function compare($a, string $op, $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            $a = $a + 0;
            $b = $b + 0;
        }
        return match ($op) {
            '>' => $a > $b,
            '>=' => $a >= $b,
            '<' => $a < $b,
            '<=' => $a <= $b,
            default => false,
        };
    }
}
