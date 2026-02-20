<?php

namespace Tests\Unit\Services\StepHandlers;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\StepHandlers\ConditionStepHandler;
use App\Services\WorkflowEngineService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ConditionStepHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeConditionStep(array $rules, array $yesChildren = [], array $noChildren = []): WorkflowStep
    {
        $step = new WorkflowStep([
            'step_type' => 'CONDITION',
            'step_config' => [
                'logic' => 'AND',
                'rules' => $rules,
            ],
        ]);
        // Give the step a predictable id and attach a dummy workflow relation (used by handler)
        $step->id = 1001;
        $workflow = new Workflow(['name' => 'Test WF']);
        $workflow->id = 5001;
        $step->setRelation('workflow', $workflow);

        // Provide branch children directly on the model so the handler doesn't try to query DB
        $step->yes_steps = $yesChildren; // accessed via $step->$branch
        $step->no_steps = $noChildren;

        return $step;
    }

    public function test_random_condition_yes_branch_when_value_meets_threshold(): void
    {
        // Simulate a "random" value in context and compare against a threshold
        $rules = [
            [
                'left' => ['type' => 'var', 'path' => '{{ rand }}'],
                'operator' => '>=',
                'right' => ['type' => 'literal', 'value' => 0.5],
            ],
        ];

        $yesChild = new WorkflowStep(['step_type' => 'ACTION']);
        $yesChild->id = 2001;
        $noChild = new WorkflowStep(['step_type' => 'ACTION']);
        $noChild->id = 2002;

        $step = $this->makeConditionStep($rules, [$yesChild], [$noChild]);

        $engine = Mockery::mock(WorkflowEngineService::class);
        // Expect the YES branch to be executed when rand >= 0.5
        $engine->shouldReceive('executeSteps')
            ->once()
            ->with($step->yes_steps, $step->workflow, Mockery::type('array'), null)
            ->andReturn([]);

        $handler = new ConditionStepHandler($engine);

        $context = ['rand' => 0.7];
        $out = $handler->handle($context, $step);

        $this->assertSame(['condition' => 'YES'], $out['parsed']);
        $this->assertArrayHasKey('context', $out);
        $this->assertArrayHasKey('condition', $out['context']);
        $this->assertArrayHasKey((string) $step->id, $out['context']['condition']);
        $this->assertTrue($out['context']['condition'][(string) $step->id]);
    }

    public function test_random_condition_no_branch_when_value_below_threshold(): void
    {
        $rules = [
            [
                'left' => ['type' => 'var', 'path' => '{{ rand }}'],
                'operator' => '>=',
                'right' => ['type' => 'literal', 'value' => 0.5],
            ],
        ];

        $yesChild = new WorkflowStep(['step_type' => 'ACTION']);
        $yesChild->id = 3001;
        $noChild = new WorkflowStep(['step_type' => 'ACTION']);
        $noChild->id = 3002;

        $step = $this->makeConditionStep($rules, [$yesChild], [$noChild]);

        $engine = Mockery::mock(WorkflowEngineService::class);
        // Expect the NO branch to be executed when rand < 0.5
        $engine->shouldReceive('executeSteps')
            ->once()
            ->with($step->no_steps, $step->workflow, Mockery::type('array'), null)
            ->andReturn([]);

        $handler = new ConditionStepHandler($engine);

        $context = ['rand' => 0.3];
        $out = $handler->handle($context, $step);

        $this->assertSame(['condition' => 'NO'], $out['parsed']);
        $this->assertArrayHasKey('context', $out);
        $this->assertArrayHasKey('condition', $out['context']);
        $this->assertArrayHasKey((string) $step->id, $out['context']['condition']);
        $this->assertFalse($out['context']['condition'][(string) $step->id]);
    }
}
