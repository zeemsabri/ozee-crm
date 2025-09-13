<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Api\Concerns\HasFinancialCalculations;
use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectExpendable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectExpendableController extends Controller
{
    use HasProjectPermissions, HasFinancialCalculations;

    public function index(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canViewProjectExpendable($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view expendables.'], 403);
        }

        $expendables = $project->budget()
            ->latest()
            ->get();

        return response()->json($expendables);
    }

    public function store(Request $request, Project $project)
    {
        $user = Auth::user();

        try {

            $this->authorize('addExpendables', $project);

        }
        catch (AuthenticationException $e)
        {
            if (!$this->canAccessProject($user, $project) || !$this->canManageProjectExpendable($user, $project)) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to create expendables.'], 403);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'user_id' => 'nullable|exists:users,id',
            'expendable_id' => 'nullable|integer',
            'expendable_type' => 'nullable|string|in:Project,Milestone,Task',
        ]);

        $status = \App\Models\ProjectExpendable::STATUS_PENDING;
        // Determine target model for morph relation
        $target = null;
        if (!empty($validated['expendable_type']) && !empty($validated['expendable_id'])) {
            switch ($validated['expendable_type']) {
                case 'Milestone':
                    $target = Milestone::where('id', $validated['expendable_id'])
                        ->where('project_id', $project->id)
                        ->firstOrFail();
                    break;
                case 'Project':
                    $status = ProjectExpendable::STATUS_ACCEPTED;
                    $target = $project; // attach to project directly
                    break;
                default:
                    $target = $project; // fallback
            }
        } else {
            $status = ProjectExpendable::STATUS_ACCEPTED;
            $target = $project; // default attach to project
        }

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'project_id' => $project->id,
            // Preserve explicit null for milestone budget and only set when provided
            'user_id' => array_key_exists('user_id', $validated) ? $validated['user_id'] : null,
            'currency' => strtoupper($validated['currency']),
            'amount' => $validated['amount'],
            'balance' => $validated['amount'],
            'status' => $status,
        ];

        // Soft-validate status via the value dictionary (does not throw when enforce=false)
        app(\App\Services\ValueSetValidator::class)->validate('ProjectExpendable','status', $payload['status']);

        $expendable = $target->expendable()->create($payload);

        return response()->json($expendable, 201);
    }

    public function update(Request $request, Project $project, ProjectExpendable $expendable)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canManageProjectExpendable($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to update expendables.'], 403);
        }

        if ($expendable->project_id !== $project->id) {
            return response()->json(['message' => 'Expendable does not belong to this project.'], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'user_id' => 'nullable|exists:users,id',
            'expendable_id' => 'nullable|integer',
            'expendable_type' => 'nullable|string|in:Project,Milestone,Task',
        ]);

        // Do not allow changing the owner relationship via update (safety): keep existing expendable_id/type.
        // However, for milestone budget updates, ensure user_id remains null.
        $updates = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'currency' => strtoupper($validated['currency']),
            'amount' => $validated['amount'],
            'balance' => $validated['amount'],
        ];

        // Only update user_id if explicitly present in the payload; budget updates pass user_id as null explicitly
        if (array_key_exists('user_id', $validated)) {
            $updates['user_id'] = $validated['user_id'];
        }

        // Guard: If editing a milestone budget (user_id is null and expendable_type is Milestone),
        // ensure the new budget is not less than the sum of already approved contracts for that milestone.
        $isMilestoneBudget = (is_null($expendable->user_id)) && (
            $expendable->expendable_type === 'App\\Models\\Milestone' || $expendable->expendable_type === 'Milestone'
        );
        if ($isMilestoneBudget) {
            /** @var Milestone|null $milestone */
            $milestone = $expendable->expendable()->first();
            if ($milestone) {
                // Sum of Accepted user-bound expendables for this milestone
                $approvedItems = $milestone->expendable()
                    ->whereNotNull('user_id')
                    ->where('status', ProjectExpendable::STATUS_ACCEPTED)
                    ->get();

                $targetCurrency = strtoupper($validated['currency']);
                $approvedTotalInTargetCurrency = 0.0;
                foreach ($approvedItems as $item) {
                    $approvedTotalInTargetCurrency += $this->convertCurrency((float)$item->amount, $item->currency, $targetCurrency);
                }

                // If the new budget is below already approved total, block it
                if ($approvedTotalInTargetCurrency > ((float)$validated['amount'] + 0.00001)) {
                    return response()->json([
                        'message' => 'Milestone budget cannot be less than the total of approved contracts.',
                        'errors' => [
                            'amount' => [
                                'Approved contracts total ' . number_format($approvedTotalInTargetCurrency, 2) . ' ' . $targetCurrency . ' exceeds the provided budget of ' . number_format((float)$validated['amount'], 2) . ' ' . $targetCurrency . '.',
                            ],
                        ],
                    ], 422);
                }
            }
        }

        $expendable->update($updates);

        return response()->json($expendable->fresh());
    }

    public function accept(Request $request, Project $project, ProjectExpendable $expendable)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($expendable->project_id !== $project->id) {
            return response()->json(['message' => 'Expendable does not belong to this project.'], 400);
        }

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Permission: approve_milestone_expendables for user-bound milestone expendables
        //             approve_expendables for budgets (user_id null) or project-level expendables
        $isMilestone = $expendable->expendable_type === 'App\\Models\\Milestone' || $expendable->expendable_type === 'Milestone';
        $isUserBound = !is_null($expendable->user_id);

        if ($isMilestone && $isUserBound) {
            if (!($user->isSuperAdmin() || $user->hasPermission('approve_milestone_expendables'))) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to approve milestone expendables.'], 403);
            }
            // Enforce milestone budget: approved user-bound expendables must not exceed milestone budget
            $milestone = $expendable->expendable()->first();
            if (!$milestone) {
                return response()->json(['message' => 'Invalid expendable: parent milestone not found.'], 400);
            }
            // Get approved milestone budget (user_id null and status Accepted)
            $budgetItem = $milestone->budget;

            if (!$budgetItem) {
                return response()->json(['message' => 'No approved milestone budget found. Approve a milestone budget first.'], 422);
            }
            // Sum of already accepted user-bound expendables for this milestone
            $approvedItems = $milestone->expendable()
                ->where('status', ProjectExpendable::STATUS_ACCEPTED)
                ->get();

            $approvedTotalInBudgetCurrency = 0.0;
            foreach ($approvedItems as $item) {
                $approvedTotalInBudgetCurrency += $this->convertCurrency((float)$item->amount, $item->currency, $budgetItem->currency);
            }
            $currentAmountInBudgetCurrency = $this->convertCurrency((float)$expendable->amount, $expendable->currency, $budgetItem->currency);
            $newTotal = round($approvedTotalInBudgetCurrency + $currentAmountInBudgetCurrency, 2);

            if ($newTotal > (float)$budgetItem->amount + 0.00001) {
                return response()->json(['message' => 'Approval would exceed the milestone budget.', 'approved_total' => $approvedTotalInBudgetCurrency, 'budget' => (float)$budgetItem->amount], 422);
            }
        } else {
            // For budgets (user_id null) and project-level expendables, require approve_expendables
            if (!($user->isSuperAdmin() || $user->hasPermission('approve_expendables'))) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to approve expendables.'], 403);
            }

            // If approving a milestone budget, ensure it doesn't exceed remaining project budget
            if ($isMilestone && is_null($expendable->user_id)) {
                $projectBudgetAmount = (float) ($project->total_expendable_amount ?? 0);
                $projectBudgetCurrency = strtoupper($project->currency ?? 'USD');
                if ($projectBudgetAmount <= 0) {
                    return response()->json(['message' => 'Project expendable budget is not set.'], 422);
                }
                // Sum of accepted milestone budgets across project
                $acceptedMilestoneBudgets = ProjectExpendable::where('project_id', $project->id)
                    ->whereNull('user_id')
                    ->where('expendable_type', 'App\\Models\\Milestone')
                    ->get();
                $acceptedBudgetsTotal = 0.0;
                foreach ($acceptedMilestoneBudgets as $b) {
                    $acceptedBudgetsTotal += $this->convertCurrency((float)$b->amount, $b->currency, $projectBudgetCurrency);
                }
                $pendingBudgetInProjectCurrency = $this->convertCurrency((float)$expendable->amount, $expendable->currency, $projectBudgetCurrency);
                if ($acceptedBudgetsTotal + $pendingBudgetInProjectCurrency > $projectBudgetAmount + 0.00001) {
                    return response()->json(['message' => 'Milestone budget exceeds the total project expendable budget.', 'project_budget' => $projectBudgetAmount], 422);
                }
            }
        }

        // Soft-validate the target status transition
        app(\App\Services\ValueSetValidator::class)->validate('ProjectExpendable','status', \App\Enums\ProjectExpendableStatus::Accepted);
        $expendable->accept($data['reason'], $user);
        return response()->json($expendable->fresh());
    }

    public function reject(Request $request, Project $project, ProjectExpendable $expendable)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($expendable->project_id !== $project->id) {
            return response()->json(['message' => 'Expendable does not belong to this project.'], 400);
        }

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $isMilestone = $expendable->expendable_type === 'App\\Models\\Milestone' || $expendable->expendable_type === 'Milestone';
        $isUserBound = !is_null($expendable->user_id);
        if ($isMilestone && $isUserBound) {
            if (!($user->isSuperAdmin() || $user->hasPermission('approve_milestone_expendables'))) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to reject milestone expendables.'], 403);
            }
        } else {
            if (!($user->isSuperAdmin() || $user->hasPermission('approve_expendables'))) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to reject expendables.'], 403);
            }
        }

        // Soft-validate the target status transition
        app(\App\Services\ValueSetValidator::class)->validate('ProjectExpendable','status', \App\Enums\ProjectExpendableStatus::Rejected);
        $expendable->reject($data['reason'], $user);
        return response()->json($expendable->fresh());
    }

    public function destroy(Request $request, Project $project, ProjectExpendable $expendable)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($expendable->project_id !== $project->id) {
            return response()->json(['message' => 'Expendable does not belong to this project.'], 400);
        }

        // Any authenticated user can delete a rejected expendable
        if ($expendable->status !== ProjectExpendable::STATUS_REJECTED) {
            return response()->json(['message' => 'Only rejected expendables can be deleted.'], 422);
        }

        $reason = $request->input('reason');
        if ($reason) {
            activity('project_expendable')
                ->performedOn($expendable)
                ->causedBy($user)
                ->withProperties(['reason' => $reason, 'status' => 'deleted'])
                ->event('expendable.deleted')
                ->log("Expendable '{$expendable->name}' deleted");
        }

        $expendable->delete();
        return response()->json(['success' => true]);
    }
}
