<?php

namespace App\Http\Controllers\Api;

use App\Events\WorkflowTriggerEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AutomationTriggerController extends Controller
{
    /**
     * Fire a workflow trigger event by name with an optional context payload.
     * POST /api/workflows/triggers/{event}
     */
    public function trigger(Request $request, string $event)
    {
        $data = $request->validate([
            'context' => ['nullable', 'array'],
            'triggering_object_id' => ['nullable', 'string'],
        ]);
        event(new WorkflowTriggerEvent($event, $data['context'] ?? [], $data['triggering_object_id'] ?? null));
        return response()->json(['status' => 'queued', 'event' => $event]);
    }
}
