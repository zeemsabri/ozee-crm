<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ProjectTimeCostReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Reports/ProjectTimeCost');
    }

    public function fetch(Request $request)
    {
        $filters = $request->only(['project_id', 'date_start', 'date_end', 'include_archived']);

        if (empty($filters['date_start'])) {
            $filters['date_start'] = Carbon::now()->startOfMonth()->toDateString();
        }
        if (empty($filters['date_end'])) {
            $filters['date_end'] = Carbon::now()->toDateString();
        }

        $startDate = Carbon::parse($filters['date_start'])->startOfDay();
        $endDate = Carbon::parse($filters['date_end'])->endOfDay();

        $includeArchived = filter_var($filters['include_archived'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $projectsQuery = Project::query();
        if ($includeArchived) {
            $projectsQuery->withTrashed();
        }

        if (!empty($filters['project_id'])) {
            $projectsQuery->where('id', $filters['project_id']);
        }

        $projects = $projectsQuery->orderBy('name')->get();

        $projectIds = $projects->pluck('id')->toArray();

        // Query User Activity time breakdown
        // Group by project_id and user_id sum(duration)
        $activities = DB::table('user_activities')
            ->join('tasks', 'user_activities.task_id', '=', 'tasks.id')
            ->join('milestones', 'tasks.milestone_id', '=', 'milestones.id')
            ->join('users', 'user_activities.user_id', '=', 'users.id')
            ->whereIn('milestones.project_id', $projectIds)
            ->whereBetween('user_activities.recorded_at', [$startDate, $endDate])
            ->select(
                'milestones.project_id',
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('SUM(user_activities.duration) as total_duration_seconds'),
                DB::raw('SUM(CASE WHEN user_activities.idle_state = "active" THEN user_activities.duration ELSE 0 END) as active_duration_seconds'),
                DB::raw('SUM(CASE WHEN user_activities.idle_state = "idle" THEN user_activities.duration ELSE 0 END) as idle_duration_seconds')
            )
            ->groupBy('milestones.project_id', 'users.id', 'users.name')
            ->get();

        $activitiesByProject = $activities->groupBy('project_id');

        // Query Transactions
        $transactions = DB::table('transactions')
            ->whereIn('project_id', $projectIds)
            ->whereBetween('payment_date', [$startDate, $endDate]) // Assuming payment_date or created_at. Let's use created_at as fallback.
            ->select(
                'project_id',
                'type',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('SUM(hours_spent) as total_hours_spent')
            )
            ->groupBy('project_id', 'type')
            ->get();
            
        // Fallback to created_at if payment_date is not primarily used
        $transactionsByCreatedAt = DB::table('transactions')
            ->whereIn('project_id', $projectIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'project_id',
                'type',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('SUM(hours_spent) as total_hours_spent')
            )
            ->groupBy('project_id', 'type')
            ->get();

        // In practice, we'll try to rely on payment_date first, or whatever the app uses.
        // We will just use created_at for simplicity here. Let's stick to created_at.
        $transactionsByProject = $transactionsByCreatedAt->groupBy('project_id');

        $report = $projects->map(function ($project) use ($activitiesByProject, $transactionsByProject) {
            $created = Carbon::parse($project->created_at);
            $deleted = $project->deleted_at ? Carbon::parse($project->deleted_at) : Carbon::now();
            $durationDays = round($created->diffInHours($deleted) / 24, 2);

            $projActivities = $activitiesByProject->get($project->id, collect());
            $projTrans = $transactionsByProject->get($project->id, collect());

            $userBreakdown = $projActivities->map(function ($act) {
                return [
                    'user_id' => $act->user_id,
                    'user_name' => $act->user_name,
                    'total_minutes' => round($act->total_duration_seconds / 60, 2),
                    'active_minutes' => round($act->active_duration_seconds / 60, 2),
                    'idle_minutes' => round($act->idle_duration_seconds / 60, 2),
                ];
            });

            $totalActiveMinutes = $userBreakdown->sum('active_minutes');
            $totalIdleMinutes = $userBreakdown->sum('idle_minutes');
            $totalMinutes = $userBreakdown->sum('total_minutes');

            $totalIncome = $projTrans->where('type', 'income')->sum('total_amount'); // assuming "income"
            $totalExpense = $projTrans->where('type', 'expense')->sum('total_amount'); // assuming "expense"
            $transHoursSpent = $projTrans->sum('total_hours_spent');

            return [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'is_archived' => !empty($project->deleted_at),
                'created_at' => $project->created_at,
                'deleted_at' => $project->deleted_at,
                'active_duration_days' => $durationDays,
                
                'user_breakdown' => $userBreakdown->values()->toArray(),
                
                'time_stats' => [
                    'total_minutes' => $totalMinutes,
                    'active_minutes' => $totalActiveMinutes,
                    'idle_minutes' => $totalIdleMinutes,
                    'transaction_hours' => $transHoursSpent, // From transaction model
                ],
                'cost_stats' => [
                    'total_income' => $totalIncome, // Just generic sums, we can refine based on `type`.
                    'total_expense' => $totalExpense,
                ]
            ];
        });

        // Global metrics
        $globalTotalMinutes = $report->sum('time_stats.total_minutes');
        $globalTotalIncome = $report->sum('cost_stats.total_income');
        $globalTotalExpense = $report->sum('cost_stats.total_expense');

        // All active + requested archived for the dropdown filter
        $allProjectsQuery = Project::orderBy('name');
        if ($includeArchived) {
            $allProjectsQuery->withTrashed();
        }
        $allProjects = $allProjectsQuery->select('id', 'name')->get()->map(fn($p) => [
            'value' => $p->id,
            'label' => $p->name
        ]);

        return response()->json([
            'projects' => $allProjects,
            'reportData' => $report->values()->toArray(),
            'filters' => $filters,
            'global_stats' => [
                'total_minutes' => $globalTotalMinutes,
                'total_income' => $globalTotalIncome,
                'total_expense' => $globalTotalExpense,
            ]
        ]);
    }
}
