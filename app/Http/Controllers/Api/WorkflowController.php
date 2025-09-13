<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesSchedules;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    use HandlesSchedules;

    /**
     * Sync workflow steps from a flat array payload.
     * - Preserves existing steps when id is numeric and matches.
     * - Creates new steps for temp_* ids and maps parent references accordingly.
     * - Deletes steps removed from the payload.
     */
    private function syncWorkflowSteps(Workflow $workflow, array $steps): void
    {
        DB::transaction(function () use ($workflow, $steps) {
            $existing = $workflow->steps()->pluck('id')->all();
            $existingSet = array_fill_keys(array_map('strval', $existing), true);

            $idMap = []; // providedId => actualId
            $seenActualIds = [];

            // First pass: create/update rows and map ids
            foreach ($steps as $s) {
                $providedId = (string)($s['id'] ?? '');
                $data = [
                    'workflow_id' => $workflow->id,
                    'step_order' => $s['step_order'] ?? null,
                    'name' => $s['name'] ?? null,
                    'step_type' => $s['step_type'] ?? null,
                    'prompt_id' => $s['prompt_id'] ?? null,
                    // Ensure NOT NULL constraint is respected; default to 0 when not provided
                    'delay_minutes' => isset($s['delay_minutes']) ? (int) $s['delay_minutes'] : 0,
                    'step_config' => is_array($s['step_config'] ?? null) ? $s['step_config'] : [],
                    'condition_rules' => $s['condition_rules'] ?? null,
                ];

                if ($providedId !== '' && ctype_digit($providedId) && isset($existingSet[$providedId])) {
                    // Update existing
                    /** @var WorkflowStep $row */
                    $row = WorkflowStep::where('workflow_id', $workflow->id)->where('id', (int)$providedId)->first();
                    if ($row) {
                        $row->fill($data)->save();
                        $idMap[$providedId] = $row->id;
                        $seenActualIds[] = $row->id;
                        continue;
                    }
                }
                // Create new
                $row = new WorkflowStep($data);
                $row->save();
                $idMap[$providedId ?: ('new_'.$row->id)] = $row->id;
                $seenActualIds[] = $row->id;
            }

            // Second pass: update parent references using the map
            foreach ($steps as $s) {
                $providedId = (string)($s['id'] ?? '');
                $actualId = $idMap[$providedId] ?? null;
                if (!$actualId) continue;
                /** @var WorkflowStep $row */
                $row = WorkflowStep::where('workflow_id', $workflow->id)->where('id', $actualId)->first();
                if (!$row) continue;
                $cfg = is_array($s['step_config'] ?? null) ? $s['step_config'] : [];
                if (array_key_exists('_parent_id', $cfg)) {
                    $oldParent = (string)($cfg['_parent_id'] ?? '');
                    $newParent = $oldParent !== '' ? ($idMap[$oldParent] ?? null) : null;
                    $cfg['_parent_id'] = $newParent;
                    $row->step_config = $cfg;
                    $row->save();
                }
            }

            // Delete removed steps (present in DB but not in payload)
            $keepIds = array_fill_keys(array_map('intval', $seenActualIds), true);
            $toDelete = array_values(array_filter($existing, fn($id) => !isset($keepIds[(int)$id])));
            if (!empty($toDelete)) {
                WorkflowStep::where('workflow_id', $workflow->id)->whereIn('id', $toDelete)->delete();
            }
        });
    }

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
            'steps' => 'sometimes|array',
        ]);

        $wfData = $data;
        unset($wfData['steps']);
        $workflow = Workflow::create($wfData);

        // Persist steps when provided
        $stepsPayload = $request->input('steps');
        if (is_array($stepsPayload)) {
            $this->syncWorkflowSteps($workflow, $stepsPayload);
        }

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
            'steps' => 'sometimes|array',
        ]);

        $wfData = $data;
        unset($wfData['steps']);
        $workflow->update($wfData);

        // Persist steps when provided
        $stepsPayload = $request->input('steps');
        if (is_array($stepsPayload)) {
            $this->syncWorkflowSteps($workflow, $stepsPayload);
        }

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
