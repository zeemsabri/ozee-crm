<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyTask;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DailyTaskController extends Controller
{
    /**
     * GET /api/daily-tasks?date=YYYY-MM-DD
     * Returns the authenticated user's work log for the given date (or today).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tz = $user->timezone ?? config('app.timezone', 'UTC');
        $date = $request->query('date', Carbon::today($tz)->toDateString());
        $targetUserId = (int) $request->query('user_id', $user->id);

        // Security check
        if ($targetUserId !== $user->id) {
            if (!$user->isSuperAdmin() && !$user->hasPermission('view_all_projects')) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $dailyTasks = DailyTask::with([
            'task' => function ($q) {
                $q->select([
                    'id', 'name', 'description', 'status', 'priority',
                    'due_date', 'milestone_id', 'assigned_to_user_id',
                ])
                ->with(['milestone:id,name,project_id', 'milestone.project:id,name', 'notes.creator']);
            }
        ])
        ->forUser($targetUserId)
        ->forDate($date)
        ->ordered()
        ->get();

        return response()->json($dailyTasks);
    }

    /**
     * GET /api/daily-tasks/history
     * Returns a summary of past days where tasks were not finished.
     */
    public function history(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tz = $user->timezone ?? config('app.timezone', 'UTC');
        $todayStr = $request->query('today', Carbon::today($tz)->toDateString());
        $days = (int) $request->query('days', 30);
        $since = Carbon::parse($todayStr)->subDays($days)->toDateString();
        $targetUserId = (int) $request->query('user_id', $user->id);

        // Security check
        if ($targetUserId !== $user->id) {
            if (!$user->isSuperAdmin() && !$user->hasPermission('view_all_projects')) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $rows = DailyTask::where('user_id', $targetUserId)
            ->where('date', '<', $todayStr)
            ->where('date', '>=', $since)
            ->with([
                'task' => function ($q) {
                    $q->select(['id', 'name', 'status', 'priority', 'milestone_id'])
                      ->with(['milestone:id,name,project_id', 'milestone.project:id,name', 'notes.creator']);
                }
            ])
            ->orderByDesc('date')
            ->orderBy('order')
            ->get();

        $grouped = $rows->groupBy(function ($dt) {
            // Ensure we handle both Carbon objects and strings safely
            $d = $dt->date;
            if ($d instanceof Carbon) return $d->toDateString();
            if (is_string($d)) return substr($d, 0, 10);
            return (string) $d;
        });

        return response()->json($grouped);
    }

    /**
     * POST /api/daily-tasks
     * Add task(s) to the current user's daily log.
     * Body: { task_ids: [1, 2, 3], date: 'YYYY-MM-DD' }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'task_ids'   => 'required|array|min:1',
            'task_ids.*' => 'integer|exists:tasks,id',
            'date'       => 'nullable|date_format:Y-m-d',
        ]);

        $user = Auth::user();
        $tz = $user->timezone ?? config('app.timezone', 'UTC');
        $date = $request->input('date', Carbon::today($tz)->toDateString());

        // Determine next order value
        $nextOrder = DailyTask::forUser($user->id)->forDate($date)->max('order') ?? -1;

        $created = [];
        foreach ($request->input('task_ids') as $taskId) {
            ++$nextOrder;
            $dt = DailyTask::firstOrCreate(
                ['user_id' => $user->id, 'task_id' => $taskId, 'date' => $date],
                ['order' => $nextOrder, 'status' => DailyTask::STATUS_PENDING]
            );
            $dt->load(['task:id,name,description,status,priority,due_date,milestone_id', 'task.milestone:id,name,project_id', 'task.milestone.project:id,name', 'task.notes.creator']);
            $created[] = $dt;
        }

        return response()->json($created, 201);
    }

    /**
     * PATCH /api/daily-tasks/{dailyTask}
     * Update status or note of a single entry.
     * Body: { status: 'completed'|'pending'|'pushed_to_next_day', note: '...' }
     */
    public function update(Request $request, DailyTask $dailyTask): JsonResponse
    {
        if ($dailyTask->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'status' => 'nullable|in:pending,completed,pushed_to_next_day',
            'note'   => 'nullable|string|max:1000',
        ]);

        $dailyTask->fill($request->only(['status', 'note']));
        $dailyTask->save();

        return response()->json($dailyTask);
    }

    /**
     * POST /api/daily-tasks/reorder
     * Save the drag-and-drop order for a specific date.
     * Body: { date: 'YYYY-MM-DD', ordered_ids: [5, 2, 8, 1] }  (DailyTask IDs in new order)
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'date'           => 'required|date_format:Y-m-d',
            'ordered_ids'    => 'required|array',
            'ordered_ids.*'  => 'integer',
        ]);

        $userId = Auth::id();
        $date   = $request->input('date');
        $ids    = $request->input('ordered_ids');

        DB::transaction(function () use ($userId, $date, $ids) {
            foreach ($ids as $position => $id) {
                DailyTask::where('id', $id)
                    ->where('user_id', $userId)
                    ->where('date', $date)
                    ->update(['order' => $position]);
            }
        });

        return response()->json(['message' => 'Order saved']);
    }

    /**
     * POST /api/daily-tasks/{dailyTask}/push-to-tomorrow
     * Mark today's entry as pushed_to_next_day and create a new entry for tomorrow.
     */
    public function pushToTomorrow(DailyTask $dailyTask): JsonResponse
    {
        if ($dailyTask->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tomorrow = Carbon::parse($dailyTask->date)->addDay()->toDateString();

        DB::transaction(function () use ($dailyTask, $tomorrow) {
            // Mark today as pushed
            $dailyTask->status = DailyTask::STATUS_PUSHED;
            $dailyTask->save();

            // Add to tomorrow's list (at the end)
            $nextOrder = DailyTask::forUser($dailyTask->user_id)->forDate($tomorrow)->max('order') ?? -1;
            DailyTask::firstOrCreate(
                ['user_id' => $dailyTask->user_id, 'task_id' => $dailyTask->task_id, 'date' => $tomorrow],
                ['order' => $nextOrder + 1, 'status' => DailyTask::STATUS_PENDING]
            );
        });

        return response()->json(['message' => 'Pushed to tomorrow', 'tomorrow' => $tomorrow]);
    }

    /**
     * DELETE /api/daily-tasks/{dailyTask}
     * Remove a task from the day's work log.
     */
    public function destroy(DailyTask $dailyTask): JsonResponse
    {
        if ($dailyTask->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $dailyTask->delete();

        return response()->json(['message' => 'Removed from daily log']);
    }
}
