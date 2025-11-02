<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesSchedules;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ScheduleApiController extends Controller
{
    use HandlesSchedules;

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_item_type' => ['required', 'string'], // 'task' | 'workflow' | FQCN
            'scheduled_item_id' => ['required', 'integer'],
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
        ]);

        $schedule = $this->persistScheduleFromArray($data);

        return response()->json([
            'id' => $schedule->id,
            'schedule' => $schedule,
        ], 201);
    }
}
