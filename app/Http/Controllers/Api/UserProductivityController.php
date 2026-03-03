<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProductivity;
use App\Services\ProductivityReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UserProductivityController extends Controller
{
    protected $reportService;

    public function __construct(ProductivityReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * List user productivity snapshots.
     */
    public function index(Request $request)
    {
        $query = UserProductivity::with('user');

        if ($request->has('user_ids')) {
            $userIds = is_array($request->user_ids) ? $request->user_ids : explode(',', $request->user_ids);
            $query->whereIn('user_id', $userIds);
        } elseif ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('date')) {
            $query->where('date', $request->date);
        }

        if ($request->boolean('all')) {
            $snapshots = $query->orderBy('date', 'desc')->get();
        } else {
            $snapshots = $query->orderBy('date', 'desc')->paginate(50);
        }

        // Add availability to each snapshot
        $snapshots->each(function ($snapshot) {
            $snapshot->availability = \App\Models\UserAvailability::where('user_id', $snapshot->user_id)
                ->whereDate('date', $snapshot->date)
                ->first();
        });

        // If requesting a single user and snapshot, return object instead of array
        if (($request->has('user_id') || (isset($userIds) && count($userIds) === 1)) && $snapshots->count() === 1) {
            return response()->json($snapshots->first());
        }

        return response()->json($snapshots);
    }

    /**
     * Create a new productivity snapshot for a user and date.
     */
    public function store(Request $request)
    {
        $v = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'recreate' => 'boolean'
        ]);

        $user = User::findOrFail($v['user_id']);
        $date = Carbon::parse($v['date'])->toDateString();

        $existing = UserProductivity::where('user_id', $user->id)
            ->where('date', $date)
            ->first();

        if ($existing && !$request->boolean('recreate')) {
            return response()->json([
                'message' => 'A productivity report already exists for this date. Please delete it first or use the recreate flag.',
                'report' => $existing
            ], 422);
        }

        $report = $this->reportService->generateDailySnapshot($user, $date);

        return response()->json([
            'message' => 'Report generated successfully.',
            'report' => $report
        ]);
    }

    /**
     * Show a specific productivity snapshot.
     */
    public function show(UserProductivity $userProductivity)
    {
        return response()->json($userProductivity->load('user'));
    }

    /**
     * Delete a productivity snapshot.
     */
    public function destroy(UserProductivity $userProductivity)
    {
        $userProductivity->delete();

        return response()->json([
            'message' => 'Report deleted successfully.'
        ]);
    }

    /**
     * Get yesterday's AI report for the authenticated user.
     */
    public function getYesterdayReport()
    {
        $user = auth()->user();
        $timezone = $user->timezone ?? config('app.timezone');
        $yesterday = Carbon::yesterday($timezone);
        $yesterdayStr = $yesterday->toDateString();

        // 1. Fetch the snapshot for yesterday
        $productivity = UserProductivity::where('user_id', $user->id)
            ->where('date', $yesterdayStr)
            ->first();

        // 2. If no report exists yet (e.g., job hasn't run or user didn't work)
        if (!$productivity || !$productivity->ai_report_json) {
            return response()->json([
                'user_report' => null,
                'date' => $yesterdayStr,
                'human_date' => $yesterday->format('F jS, Y'),
                'status' => 'not_found'
            ]);
        }

        // 3. Extract the user-facing report
        $aiData = $productivity->ai_report_json;
        $userReport = $aiData['user_report'] ?? null;
        $userQuestion = $aiData['user_question'] ?? 'How was your day?';
        
        // Get existing feedback if any
        $feedback = $productivity->feedback_json ?? [];

        return response()->json([
            'user_report' => $userReport,
            'user_question' => $userQuestion,
            'date' => $yesterdayStr,
            'human_date' => $yesterday->format('F jS, Y'),
            'status' => $productivity->status,
            'user_feedback' => $feedback['user_feedback'] ?? ''
        ]);
    }

    /**
     * Save user feedback for yesterday's productivity.
     */
    public function saveYesterdayFeedback(Request $request)
    {
        $request->validate([
            'feedback' => 'required|string|max:1000',
        ]);

        $user = auth()->user();
        $timezone = $user->timezone ?? config('app.timezone');
        $yesterdayStr = Carbon::yesterday($timezone)->toDateString();

        $productivity = UserProductivity::where('user_id', $user->id)
            ->where('date', $yesterdayStr)
            ->first();

        if (!$productivity) {
            return response()->json(['message' => 'Productivity record not found for yesterday.'], 404);
        }

        $feedback = $productivity->feedback_json ?? [];
        $feedback['user_feedback'] = $request->feedback;
        $feedback['user_feedback_at'] = now()->toDateTimeString();

        $productivity->feedback_json = $feedback;
        $productivity->save();

        return response()->json([
            'message' => 'Feedback saved successfully.',
            'user_feedback' => $request->feedback
        ]);
    }

    /**
     * Update admin feedback for a productivity snapshot.
     */
    public function updateFeedback(Request $request, UserProductivity $userProductivity)
    {
        $v = $request->validate([
            'admin_feedback' => 'nullable|string',
            'task_feedback' => 'nullable|array',
        ]);

        $feedback = $userProductivity->feedback_json ?? [];

        if ($request->filled('admin_feedback')) {
            $adminLogs = $feedback['admin_feedbacks'] ?? [];
            $adminLogs[] = [
                'comment' => $v['admin_feedback'],
                'at' => now()->toDateTimeString(),
                'by_id' => auth()->id(),
                'by_name' => auth()->user()->name,
            ];
            $feedback['admin_feedbacks'] = $adminLogs;

            // Keep legacy field for compatibility if needed
            $feedback['admin_feedback'] = $v['admin_feedback'];
            $feedback['admin_feedback_at'] = now()->toDateTimeString();
            $feedback['admin_feedback_by_name'] = auth()->user()->name;
        }

        if ($request->has('task_feedback') && is_array($v['task_feedback'])) {
            $taskLogs = $feedback['task_feedbacks'] ?? [];
            $legacyTaskFeedback = $feedback['task_feedback'] ?? [];
            if (!is_array($legacyTaskFeedback)) $legacyTaskFeedback = [];

            foreach ($v['task_feedback'] as $taskId => $comment) {
                if (empty($comment)) continue;

                $logsForTask = $taskLogs[$taskId] ?? [];
                $logsForTask[] = [
                    'comment' => $comment,
                    'at' => now()->toDateTimeString(),
                    'by_id' => auth()->id(),
                    'by_name' => auth()->user()->name,
                ];
                $taskLogs[$taskId] = $logsForTask;

                // Keep legacy field for compatibility
                $legacyTaskFeedback[$taskId] = $comment;
            }
            $feedback['task_feedbacks'] = $taskLogs;
            $feedback['task_feedback'] = $legacyTaskFeedback;
        }

        $userProductivity->feedback_json = $feedback;
        $userProductivity->save();

        return response()->json([
            'message' => 'Feedback updated successfully.',
            'feedback' => $feedback
        ]);
    }
}
