<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalFlow;
use App\Models\ApprovalInstance;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class ApprovalFlowController extends Controller
{
    public function index(): Response
    {
        $flows = ApprovalFlow::with(['project:id,name', 'steps'])
            ->latest()
            ->get();

        return Inertia::render('Admin/ApprovalFlows/Index', [
            'flows' => $flows,
        ]);
    }

    public function create(): Response
    {
        $projects = Project::select('id', 'name')->orderBy('name')->get();
        $roles = Role::select('id', 'name')->orderBy('name')->get();
        $users = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/ApprovalFlows/CreateEdit', [
            'mode' => 'create',
            'projects' => $projects,
            'roles' => $roles,
            'users' => $users,
            'approvableTypes' => $this->approvableTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'approvable_type' => 'required|string|max:255',
            'project_id' => 'nullable|exists:projects,id',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'steps' => 'required|array|min:1',
            'steps.*.approver_type' => 'required|in:user,role',
            'steps.*.approver_role_id' => 'nullable|exists:roles,id',
            'steps.*.approver_user_id' => 'nullable|exists:users,id',
            'steps.*.label' => 'nullable|string|max:255',
        ]);

        foreach ($validated['steps'] as $index => $stepData) {
            if (($stepData['approver_type'] ?? null) === 'role' && empty($stepData['approver_role_id'])) {
                return back()->withErrors([
                    "steps.$index.approver_role_id" => 'Please select a role for this step.',
                ])->withInput();
            }

            if (($stepData['approver_type'] ?? null) === 'user' && empty($stepData['approver_user_id'])) {
                return back()->withErrors([
                    "steps.$index.approver_user_id" => 'Please select a user for this step.',
                ])->withInput();
            }
        }

        DB::transaction(function () use ($validated) {
            $flow = ApprovalFlow::create([
                'name' => $validated['name'],
                'approvable_type' => $validated['approvable_type'],
                'project_id' => $validated['project_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'is_default' => $validated['is_default'] ?? false,
            ]);

            if ($flow->is_default) {
                ApprovalFlow::query()
                    ->where('id', '!=', $flow->id)
                    ->where('approvable_type', $flow->approvable_type)
                    ->where('project_id', $flow->project_id)
                    ->update(['is_default' => false]);
            }

            $this->syncSteps($flow, $validated['steps']);
        });

        return redirect()->route('admin.approval-flows.index')->with('success', 'Approval flow created successfully.');
    }

    public function edit(ApprovalFlow $approvalFlow): Response
    {
        $approvalFlow->load('steps');
        $projects = Project::select('id', 'name')->orderBy('name')->get();
        $roles = Role::select('id', 'name')->orderBy('name')->get();
        $users = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/ApprovalFlows/CreateEdit', [
            'mode' => 'edit',
            'flow' => $approvalFlow,
            'projects' => $projects,
            'roles' => $roles,
            'users' => $users,
            'approvableTypes' => $this->approvableTypeOptions(),
        ]);
    }

    public function update(Request $request, ApprovalFlow $approvalFlow): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'approvable_type' => 'required|string|max:255',
            'project_id' => 'nullable|exists:projects,id',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'resync_pending' => 'boolean',
            'steps' => 'required|array|min:1',
            'steps.*.approver_type' => 'required|in:user,role',
            'steps.*.approver_role_id' => 'nullable|exists:roles,id',
            'steps.*.approver_user_id' => 'nullable|exists:users,id',
            'steps.*.label' => 'nullable|string|max:255',
        ]);

        foreach ($validated['steps'] as $index => $stepData) {
            if (($stepData['approver_type'] ?? null) === 'role' && empty($stepData['approver_role_id'])) {
                return back()->withErrors([
                    "steps.$index.approver_role_id" => 'Please select a role for this step.',
                ])->withInput();
            }

            if (($stepData['approver_type'] ?? null) === 'user' && empty($stepData['approver_user_id'])) {
                return back()->withErrors([
                    "steps.$index.approver_user_id" => 'Please select a user for this step.',
                ])->withInput();
            }
        }

        DB::transaction(function () use ($approvalFlow, $validated) {
            $approvalFlow->update([
                'name' => $validated['name'],
                'approvable_type' => $validated['approvable_type'],
                'project_id' => $validated['project_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'is_default' => $validated['is_default'] ?? false,
            ]);

            if ($approvalFlow->is_default) {
                ApprovalFlow::query()
                    ->where('id', '!=', $approvalFlow->id)
                    ->where('approvable_type', $approvalFlow->approvable_type)
                    ->where('project_id', $approvalFlow->project_id)
                    ->update(['is_default' => false]);
            }

            $this->syncSteps($approvalFlow, $validated['steps']);
        });

        if ($request->boolean('resync_pending')) {
            $this->resyncPendingInstances($approvalFlow);
        }

        return redirect()->route('admin.approval-flows.index')->with('success', 'Approval flow updated successfully.');
    }

    public function destroy(ApprovalFlow $approvalFlow): RedirectResponse
    {
        $approvalFlow->steps()->delete();
        $approvalFlow->delete();

        return redirect()->route('admin.approval-flows.index')->with('success', 'Approval flow deleted successfully.');
    }

    private function resyncPendingInstances(ApprovalFlow $flow): void
    {
        // 1. Only re-sync instances where no steps have been acted on yet
        $instances = ApprovalInstance::query()
            ->where('approval_flow_id', $flow->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereDoesntHave('steps', fn($q) => $q->whereNotNull('acted_at'))
            ->get();

        $freshSteps = $flow->fresh('steps')->steps;

        if ($freshSteps->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($instances, $flow, $freshSteps) {
            // Re-sync existing instances
            foreach ($instances as $instance) {
                $instance->steps()->delete();

                foreach ($freshSteps as $step) {
                    $instance->steps()->create([
                        'step_order' => $step->step_order,
                        'approver_type' => $step->approver_type,
                        'approver_role_id' => $step->approver_role_id,
                        'approver_user_id' => $step->approver_user_id,
                        'label' => $step->label,
                        'status' => 'pending',
                    ]);
                }

                $instance->current_step_order = $freshSteps->first()->step_order;
                $instance->status = 'in_progress';
                $instance->save();
            }

            // 2. Assign this flow to bills/invoices that have NO flow yet and are pending
            $modelClass = $flow->approvable_type;
            if (class_exists($modelClass)) {
                $query = $modelClass::query()->doesntHave('approvalInstance');

                // If this is a project-specific flow, only assign to items of this project
                if ($flow->project_id) {
                    $query->where('project_id', $flow->project_id);
                }

                if ($modelClass === \App\Models\Bill::class) {
                    $query->where('status', \App\Enums\BillStatus::PendingApproval);
                } elseif ($modelClass === \App\Models\Invoice::class) {
                    $query->where('status', 'pending_approval');
                }

                $unassignedModels = $query->get();

                foreach ($unassignedModels as $model) {
                    // Check if THIS flow is the one that SHOULD be assigned (e.g., if this is default global, 
                    // make sure they don't have a project-specific flow available instead)
                    $resolvedFlow = ApprovalFlow::query()
                        ->where('approvable_type', $modelClass)
                        ->where('is_active', true)
                        ->where(function ($q) use ($model) {
                            $q->where('project_id', $model->project_id)
                                ->orWhere(function ($inner) {
                                    $inner->whereNull('project_id')->where('is_default', true);
                                });
                        })
                        ->orderByRaw('project_id is null')
                        ->first();

                    if ($resolvedFlow && $resolvedFlow->id === $flow->id) {
                        $instance = $model->approvalInstance()->create([
                            'approval_flow_id' => $flow->id,
                            'current_step_order' => $freshSteps->first()->step_order,
                            'status' => 'in_progress',
                        ]);

                        foreach ($freshSteps as $step) {
                            $instance->steps()->create([
                                'step_order' => $step->step_order,
                                'approver_type' => $step->approver_type,
                                'approver_role_id' => $step->approver_role_id,
                                'approver_user_id' => $step->approver_user_id,
                                'label' => $step->label,
                                'status' => 'pending',
                            ]);
                        }
                    }
                }
            }
        });
    }

    private function syncSteps(ApprovalFlow $flow, array $steps): void
    {
        $flow->steps()->delete();

        foreach (array_values($steps) as $index => $stepData) {
            $flow->steps()->create([
                'step_order' => $index + 1,
                'approver_type' => $stepData['approver_type'],
                'approver_role_id' => $stepData['approver_type'] === 'role' ? $stepData['approver_role_id'] : null,
                'approver_user_id' => $stepData['approver_type'] === 'user' ? $stepData['approver_user_id'] : null,
                'label' => $stepData['label'] ?? null,
            ]);
        }
    }

    private function approvableTypeOptions(): array
    {
        $defaults = collect([
            ['value' => Bill::class, 'label' => 'Bill'],
            ['value' => Invoice::class, 'label' => 'Invoice'],
        ]);

        $fromDatabase = ApprovalFlow::query()
            ->select('approvable_type')
            ->distinct()
            ->pluck('approvable_type')
            ->filter()
            ->map(function (string $type) {
                $segments = explode('\\\\', $type);

                return [
                    'value' => $type,
                    'label' => end($segments) ?: $type,
                ];
            });

        return $defaults
            ->concat($fromDatabase)
            ->unique('value')
            ->values()
            ->all();
    }
}
