<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesSchedules;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Services\WorkflowEngineService;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    use HandlesSchedules;
    public function index()
    {
        return response()->json(Workflow::with('steps')->orderBy('id', 'desc')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $workflow = Workflow::create($data);

        // Optionally create a schedule when provided as nested payload
        $attachedScheduleId = null;
        $schedulePayload = $request->input('schedule');
        if (is_array($schedulePayload) && !empty($schedulePayload)) {
            $rules = [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'start_at' => ['required', 'date'],
                'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
                'mode' => ['required', 'in:once,daily,weekly,monthly,yearly,cron'],
                'time' => ['nullable', 'string'],
                'days_of_week' => ['array'],
                'days_of_week.*' => ['integer', 'between:0,6'],
                'day_of_month' => ['nullable', 'integer', 'between:1,31'],
                'nth' => ['nullable', 'integer', 'between:1,5'],
                'dow_for_monthly' => ['nullable', 'integer', 'between:0,6'],
                'month' => ['nullable', 'integer', 'between:1,12'],
                'cron' => ['nullable', 'string'],
            ];
            $payload = validator($schedulePayload, $rules)->validate();
            $payload['scheduled_item_type'] = 'workflow';
            $payload['scheduled_item_id'] = $workflow->id;
            $schedule = $this->persistScheduleFromArray($payload);
            $attachedScheduleId = $schedule->id;
        }

        $wf = $workflow->load('steps');
        $arr = $wf->toArray();
        if ($attachedScheduleId) {
            $arr['attached_schedule_id'] = $attachedScheduleId;
        }
        return response()->json($arr, 201);
    }

    public function show(Workflow $workflow)
    {
        return response()->json($workflow->load('steps'));
    }

    public function update(Request $request, Workflow $workflow)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $workflow->update($data);

        // Optionally create a schedule when provided as nested payload during update
        $attachedScheduleId = null;
        $schedulePayload = $request->input('schedule');
        if (is_array($schedulePayload) && !empty($schedulePayload)) {
            $rules = [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'start_at' => ['required', 'date'],
                'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
                'mode' => ['required', 'in:once,daily,weekly,monthly,yearly,cron'],
                'time' => ['nullable', 'string'],
                'days_of_week' => ['array'],
                'days_of_week.*' => ['integer', 'between:0,6'],
                'day_of_month' => ['nullable', 'integer', 'between:1,31'],
                'nth' => ['nullable', 'integer', 'between:1,5'],
                'dow_for_monthly' => ['nullable', 'integer', 'between:0,6'],
                'month' => ['nullable', 'integer', 'between:1,12'],
                'cron' => ['nullable', 'string'],
            ];
            $payload = validator($schedulePayload, $rules)->validate();
            $payload['scheduled_item_type'] = 'workflow';
            $payload['scheduled_item_id'] = $workflow->id;
            $schedule = $this->persistScheduleFromArray($payload);
            $attachedScheduleId = $schedule->id;
        }

        $wf = $workflow->load('steps');
        $arr = $wf->toArray();
        if ($attachedScheduleId) {
            $arr['attached_schedule_id'] = $attachedScheduleId;
        }
        return response()->json($arr);
    }

    public function destroy(Workflow $workflow)
    {
        $workflow->delete();
        return response()->noContent();
    }

    public function run(Request $request, Workflow $workflow, WorkflowEngineService $engine)
    {
        $data = $request->validate([
            'context' => ['nullable', 'array'],
        ]);
        $context = $data['context'] ?? [];
        $result = $engine->execute($workflow->load('steps'), $context);
        return response()->json($result);
    }
}
