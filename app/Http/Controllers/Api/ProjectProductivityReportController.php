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
        $filters = $request->only(['project_ids', 'date_start', 'date_end', 'active_only']);

        if (!isset($filters['active_only'])) {
            $filters['active_only'] = 'true';
        }

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
            ->when($filters['active_only'] === 'true', function($q) {
                $q->whereHas('projectServices', function($sq) {
                    $sq->where('status', 'active');
                });
            })
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
                      ->orWhereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()])
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

            // 4. Chat messages
            $chatMessages = \App\Models\ChatMessage::where('project_id', $project->id)
                ->with(['user', 'client'])
                ->latest()
                ->limit(10)
                ->get();

            return [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'project_notes' => $projectNotes->map(function($note) {
                    return [
                        'id' => $note->id,
                        'content' => $note->content,
                        'type' => $note->type,
                        'creator_name' => $note->creator?->name ?? 'System',
                        'created_at' => $note->created_at,
                    ];
                }),
                'tasks' => $tasks->map(function($task) use ($project) {
                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'description' => $task->description,
                        'status' => $task->status,
                        'assigned_to' => $task->assignedTo?->name,
                        'due_date' => $task->due_date,
                        'milestone' => $task->milestone?->name,
                        'project_id' => $project->id, // Added project_id
                        'notes' => $task->notes->map(function($note) {
                            return [
                                'id' => $note->id,
                                'content' => $note->content,
                                'creator_name' => $note->creator?->name ?? 'System',
                                'created_at' => $note->created_at,
                            ];
                        }),
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
                'messages' => $chatMessages->map(function($msg) {
                    $userName = $msg->user?->name ?? ($msg->client?->name ?? ($msg->meta_data['telegram_from']['first_name'] ?? 'System'));
                    return [
                        'id' => $msg->id,
                        'message' => $msg->message,
                        'user' => $userName,
                        'created_at' => $msg->created_at->toDateTimeString(),
                        'source' => $msg->source,
                    ];
                })->reverse()->values(),
                'data' => $project->data,
                'has_activity' => $projectNotes->isNotEmpty() || $tasks->isNotEmpty() || $emails->isNotEmpty()
            ];
        });

        // Get all projects for filters
        $allProjectsQuery = Project::query();
        if ($filters['active_only'] === 'true') {
            $allProjectsQuery->whereHas('projectServices', function($sq) {
                $sq->where('status', 'active');
            });
        }
        $allProjects = $allProjectsQuery->select('id', 'name')->orderBy('name')->get()->map(fn($p) => [
            'value' => $p->id,
            'label' => $p->name
        ]);

        return response()->json([
            'projects' => $allProjects,
            'reportData' => $report,
            'filters' => $filters
        ]);
    }

    public function messages(Request $request, Project $project)
    {
        $beforeId = $request->input('before_id');
        $limit = $request->input('limit', 10);

        $messages = \App\Models\ChatMessage::where('project_id', $project->id)
            ->with(['user', 'client'])
            ->when($beforeId, function($q) use ($beforeId) {
                $q->where('id', '<', $beforeId);
            })
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json($messages->map(function($msg) {
            $userName = $msg->user?->name ?? ($msg->client?->name ?? ($msg->meta_data['telegram_from']['first_name'] ?? 'System'));
            return [
                'id' => $msg->id,
                'message' => $msg->message,
                'user' => $userName,
                'created_at' => $msg->created_at->toDateTimeString(),
                'source' => $msg->source,
            ];
        }));
    }
}
