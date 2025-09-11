<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkflowStepController extends Controller
{
    public function index()
    {
        return response()->json(WorkflowStep::with(['workflow', 'prompt'])->orderByDesc('id')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'workflow_id' => ['required', 'integer', 'exists:workflows,id'],
            'step_order' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:255'],
            'step_type' => ['sometimes', 'string', 'max:255'],
            'prompt_id' => ['nullable', 'integer', 'exists:prompts,id'],
            'step_config' => ['nullable', 'array'],
            'condition_rules' => ['nullable', 'array'],
            'delay_minutes' => ['sometimes', 'integer', 'min:0'],
        ]);

        $step = WorkflowStep::create($data);
        return response()->json($step->load(['workflow', 'prompt']), 201);
    }

    public function show(WorkflowStep $workflow_step)
    {
        return response()->json($workflow_step->load(['workflow', 'prompt']));
    }

    public function update(Request $request, WorkflowStep $workflow_step)
    {
        $data = $request->validate([
            'workflow_id' => ['sometimes', 'integer', 'exists:workflows,id'],
            'step_order' => ['sometimes', 'integer', 'min:0'],
            'name' => ['sometimes', 'string', 'max:255'],
            'step_type' => ['sometimes', 'string', 'max:255'],
            'prompt_id' => ['nullable', 'integer', 'exists:prompts,id'],
            'step_config' => ['nullable', 'array'],
            'condition_rules' => ['nullable', 'array'],
            'delay_minutes' => ['sometimes', 'integer', 'min:0'],
        ]);

        $workflow_step->update($data);
        return response()->json($workflow_step->load(['workflow', 'prompt']));
    }

    public function destroy(WorkflowStep $workflow_step)
    {
        $workflow_step->delete();
        return response()->noContent();
    }
}
