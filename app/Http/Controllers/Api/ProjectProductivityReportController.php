<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\Email;
use App\Models\ProjectNote;
use App\Models\Context;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectProductivityReportController extends Controller
{
    public function render()
    {
        return Inertia::render('Admin/Productivity/ProjectIndex');
    }

    public function index(Request $request)
    {
        $filters = $request->only(['project_ids', 'date_start', 'date_end']);

        if (empty($filters['date_start'])) {
            $filters['date_start'] = Carbon::now()->startOfMonth()->toDateString();
        }
        if (empty($filters['date_end'])) {
            $filters['date_end'] = Carbon::now()->toDateString();
        }

        $projectIds = $filters['project_ids'] ?? [];
        if (is_string($projectIds)) {
            $projectIds = explode(',', $projectIds);
        }

        $startDate = Carbon::parse($filters['date_start'])->startOfDay();
        $endDate = Carbon::parse($filters['date_end'])->endOfDay();

        $projects = Project::query()
            ->when(!empty($projectIds), fn($q) => $q->whereIn('id', $projectIds))
            ->orderBy('name')
            ->get();

        $report = $projects->map(function ($project) use ($startDate, $endDate) {
            // 1. Project Notes
            $projectNotes = ProjectNote::where('project_id', $project->id)
                ->where(function($q) {
                    $q->whereNull('noteable_type')
                      ->orWhere('noteable_type', Project::class);
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->with('creator')
                ->latest()
                ->get();

            // 2. Tasks with their notes
            // We want tasks that had any activity or were updated in this period
            $tasks = Task::whereHas('milestone', function($q) use ($project) {
                    $q->where('project_id', $project->id);
                })
                ->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('updated_at', [$startDate, $endDate])
                      ->orWhereHas('notes', function($nq) use ($startDate, $endDate) {
                          $nq->whereBetween('created_at', [$startDate, $endDate]);
                      });
                })
                ->with(['notes.creator', 'assignedTo', 'milestone'])
                ->get();

            // 3. Emails with their contexts
            $emails = Email::whereHas('conversation', function($q) use ($project) {
                    $q->where('project_id', $project->id);
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->with(['contexts', 'sender'])
                ->latest()
                ->get();

            return [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'project_notes' => $projectNotes,
                'tasks' => $tasks->map(function($task) {
                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'status' => $task->status,
                        'assigned_to' => $task->assignedTo?->name,
                        'milestone' => $task->milestone?->name,
                        'notes' => $task->notes,
                        'updated_at' => $task->updated_at,
                    ];
                }),
                'emails' => $emails->map(function($email) {
                    return [
                        'id' => $email->id,
                        'subject' => $email->subject,
                        'type' => $email->type,
                        'status' => $email->status,
                        'sender' => $email->sender?->name ?? $email->sender_id,
                        'created_at' => $email->created_at,
                        'contexts' => $email->contexts,
                    ];
                }),
                'has_activity' => $projectNotes->isNotEmpty() || $tasks->isNotEmpty() || $emails->isNotEmpty()
            ];
        });

        // Get all projects for filters
        $allProjects = Project::select('id', 'name')->orderBy('name')->get()->map(fn($p) => [
            'value' => $p->id,
            'label' => $p->name
        ]);

        return response()->json([
            'projects' => $allProjects,
            'reportData' => $report,
            'filters' => $filters
        ]);
    }
}
