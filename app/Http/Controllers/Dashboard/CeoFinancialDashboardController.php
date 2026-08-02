<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CeoFinancialDashboardController extends Controller
{
    public function __construct(
        private readonly \App\Services\ProfitLossService $profitLossService
    ) {}

    public function index(Request $request)
    {
        // Require permission
        if (! $request->user()->hasPermission('view-ceo-dashboard')) {
            abort(403, 'Unauthorized action.');
        }

        // Default to Previous Saturday to Last Friday
        // E.g., if today is Wednesday, last Friday was 5 days ago, previous Sat was 11 days ago.
        $defaultEnd = now()->previous(\Carbon\Carbon::FRIDAY)->endOfDay();
        $defaultStart = $defaultEnd->copy()->subDays(6)->startOfDay(); // 6 days before Friday is Saturday

        $startDateStr = $request->input('start_date', $defaultStart->format('Y-m-d'));
        $endDateStr = $request->input('end_date', $defaultEnd->format('Y-m-d'));

        $startDate = \Carbon\Carbon::parse($startDateStr)->startOfDay();
        $endDate = \Carbon\Carbon::parse($endDateStr)->endOfDay();

        $dashboardData = $this->profitLossService->getDashboardData($startDate, $endDate);
        $timelineData = $this->profitLossService->getCashManagementTimeline();
        
        $currencyRates = \App\Models\CurrencyRate::all()->mapWithKeys(function ($rate) {
            return [$rate->currency_code => $rate->rate_to_usd];
        });

        // Also fetch active team members for the instruction modal assignee dropdown
        $users = \App\Models\User::get(['id', 'name']);

        return \Inertia\Inertia::render('Dashboard/CeoFinancial', [
            'dashboardData' => $dashboardData,
            'timelineData' => $timelineData,
            'currencyRates' => $currencyRates,
            'filters' => [
                'start_date' => $startDateStr,
                'end_date' => $endDateStr,
            ],
            'users' => $users,
        ]);
    }

    public function storeInstruction(Request $request)
    {
        if (! $request->user()->hasPermissionTo('view-ceo-dashboard')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'instructable_type' => 'required|string|in:bill,invoice',
            'instructable_id' => 'required|integer',
            'directive' => 'required|string',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $modelClass = $validated['instructable_type'] === 'bill' ? \App\Models\Bill::class : \App\Models\Invoice::class;
        $noteable = $modelClass::findOrFail($validated['instructable_id']);
        
        // Ensure noteable has project
        $project = $noteable->project;
        if (! $project) {
            return back()->with('error', 'Item must belong to a project to add instructions.');
        }

        $context = [
            'directive' => $validated['directive'],
            'assigned_to' => $validated['assigned_to'],
        ];

        // Combine directive and notes for the content
        $content = "Directive: {$validated['directive']}";
        if (!empty($validated['notes'])) {
            $content .= "\n\nNotes: {$validated['notes']}";
        }

        // ProjectNote::createAndNotify automatically sets type 'note' and handles notification if configured
        $note = \App\Models\ProjectNote::createAndNotify($project, $content, [
            'type' => \App\Models\ProjectNote::COMMENT,
            'noteable' => $noteable,
        ]);

        $note->context = $context;
        $note->save();

        // If assigned_to is provided, create a Task
        if (!empty($validated['assigned_to'])) {
            $taskType = \App\Models\TaskType::firstOrCreate(['name' => 'Accounting Instruction']);
            
            // Try to find a support milestone or use the first one
            $milestone = $project->supportMilestone() ?? $project->milestones()->first();
            
            if ($milestone) {
                \App\Models\Task::create([
                    'name' => 'Instruction: ' . $validated['directive'],
                    'description' => "Please review the {$validated['instructable_type']} #{$validated['instructable_id']}.\n\n{$content}",
                    'project_id' => $project->id,
                    'milestone_id' => $milestone->id,
                    'assigned_to_user_id' => $validated['assigned_to'],
                    'due_date' => now()->startOfDay(),
                    'status' => \App\Enums\TaskStatus::ToDo,
                    'priority' => 'high', // Instructions from CEO should be high
                    'task_type_id' => $taskType->id,
                    'creator_id' => $request->user()->id,
                    'creator_type' => get_class($request->user()),
                    'source' => 'project_note',
                    'source_id' => $note->id,
                ]);
            }
        }

        return back()->with('success', 'Instruction added successfully.');
    }
}
