<?php

namespace App\Services;

use App\Models\ApprovalFlow;
use App\Models\Bill;
use Illuminate\Support\Facades\DB;

/**
 * Starts a bill's approval instance from the applicable ApprovalFlow.
 *
 * Extracted from BillController::initializeBillApprovalInstance so the public
 * (guest) bill-upload endpoint starts the exact same flow as an internally created
 * bill, rather than keeping a second copy of the logic that could drift.
 */
class BillApprovalFlowService
{
    /**
     * Create the approval instance and its steps for a newly created bill.
     *
     * No-op when the project has no active bill flow (or the flow has no steps) — the
     * bill then simply stays `pending_approval` until a Super Admin approves it
     * directly, which is what BillController::approve already falls back to.
     */
    public function initialize(Bill $bill, int $projectId): void
    {
        $flow = ApprovalFlow::query()
            ->where('approvable_type', Bill::class)
            ->where('is_active', true)
            ->where(function ($query) use ($projectId) {
                $query->where('project_id', $projectId)
                    ->orWhere(function ($inner) {
                        $inner->whereNull('project_id')->where('is_default', true);
                    });
            })
            ->with('steps')
            // A project-specific flow wins over the global default.
            ->orderByRaw('project_id is null')
            ->first();

        if (! $flow || $flow->steps->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($bill, $flow) {
            $instance = $bill->approvalInstance()->create([
                'approval_flow_id' => $flow->id,
                'current_step_order' => $flow->steps->first()->step_order,
                'status' => 'in_progress',
            ]);

            foreach ($flow->steps as $step) {
                $instance->steps()->create([
                    'step_order' => $step->step_order,
                    'approver_type' => $step->approver_type,
                    'approver_role_id' => $step->approver_role_id,
                    'approver_user_id' => $step->approver_user_id,
                    'label' => $step->label,
                    'status' => 'pending',
                ]);
            }
        });
    }
}
