<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Project;
use App\Models\ProjectExpendable;
use App\Enums\BillStatus;
use App\Enums\ProjectExpendableStatus;
use App\Models\ApprovalFlow;
use App\Models\ApprovalInstance;
use App\Services\XeroBillService;
use App\Services\XeroAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillController extends Controller
{
    use HasProjectPermissions;

    public function __construct(
        private readonly XeroBillService $xeroBillService,
        private readonly XeroAttachmentService $xeroAttachmentService
    ) {}

    public function index(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $bills = Bill::where('project_id', $project->id)
            ->with(['contractor', 'expendable', 'transactionType', 'approvalInstance.steps'])
            ->latest()
            ->get();

        return response()->json($bills);
    }

    public function store(Request $request, Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'contractor_id' => 'required|exists:users,id',
            'project_expendable_id' => 'required|exists:project_expendables,id',
            'transaction_type_id' => 'required|exists:transaction_types,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_details' => 'required|array',
            'payment_details.payment_method' => 'required|string|max:50',
            'payment_details.account_name' => 'required|string|max:255',
            'payment_details.account_number' => 'required|string|max:255',
            'payment_details.bank_name' => 'nullable|string|max:255',
            'payment_details.bsb' => 'nullable|string|max:255',
            'payment_details.swift_code' => 'nullable|string|max:255',
            'payment_details.iban' => 'nullable|string|max:255',
            'payment_details.notes' => 'nullable|string|max:1000',
        ]);

        $expendable = ProjectExpendable::findOrFail($validated['project_expendable_id']);

        if ((int) $expendable->project_id !== (int) $project->id) {
            return response()->json([
                'message' => 'Selected contract does not belong to this project.',
                'errors' => [
                    'project_expendable_id' => ['Selected contract does not belong to this project.'],
                ],
            ], 422);
        }

        if ((int) $expendable->user_id !== (int) $validated['contractor_id']) {
            return response()->json([
                'message' => 'Selected contractor does not match the contract owner.',
                'errors' => [
                    'contractor_id' => ['Selected contractor does not match the contract owner.'],
                ],
            ], 422);
        }

        $contractStatus = $expendable->status instanceof \BackedEnum
            ? $expendable->status->value
            : (string) $expendable->status;

        if ($contractStatus !== ProjectExpendableStatus::Accepted->value) {
            return response()->json([
                'message' => 'Bills can only be created for accepted contracts.',
                'errors' => [
                    'project_expendable_id' => ['Contract must be accepted before bill creation.'],
                ],
            ], 422);
        }

        if ($validated['amount'] > $expendable->balance) {
            return response()->json([
                'message' => 'Bill amount exceeds the remaining balance of the contract.',
                'errors' => [
                    'amount' => ["Remaining balance is {$expendable->balance}."]
                ]
            ], 422);
        }

        $bill = DB::transaction(function () use ($project, $validated) {
            $bill = Bill::create([
                'project_id' => $project->id,
                'contractor_id' => $validated['contractor_id'],
                'project_expendable_id' => $validated['project_expendable_id'],
                'transaction_type_id' => $validated['transaction_type_id'],
                'amount' => $validated['amount'],
                'status' => BillStatus::PendingApproval,
            ]);

            $paymentDetails = $validated['payment_details'];

            $bill->paymentDetail()->create([
                'contractor_id' => $validated['contractor_id'],
                'payment_method' => $paymentDetails['payment_method'],
                'details' => [
                    'account_name' => $paymentDetails['account_name'],
                    'account_number' => $paymentDetails['account_number'],
                    'bank_name' => $paymentDetails['bank_name'] ?? null,
                    'bsb' => $paymentDetails['bsb'] ?? null,
                    'swift_code' => $paymentDetails['swift_code'] ?? null,
                    'iban' => $paymentDetails['iban'] ?? null,
                    'notes' => $paymentDetails['notes'] ?? null,
                ],
            ]);

            return $bill;
        });

        $this->initializeBillApprovalInstance($bill, $project->id);

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json($bill->fresh(['paymentDetail']), 201);
    }

    public function approve(Project $project, Bill $bill)
    {
        $user = Auth::user();

        if ((int) $bill->project_id !== (int) $project->id) {
            return response()->json(['message' => 'Bill does not belong to this project.'], 400);
        }

        if ($bill->status !== BillStatus::PendingApproval) {
            return response()->json(['message' => 'Bill is not in pending status.'], 400);
        }

        $instance = $bill->approvalInstance()->with('steps')->first();

        if ($instance) {
            return $this->approveThroughFlow($user, $bill, $instance);
        }

        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can approve bills.'], 403);
        }

        $this->performFinalBillApproval($bill);

        return response()->json($bill->fresh(['contractor', 'expendable', 'transactionType', 'approvalInstance.steps']));
    }

    public function void(Project $project, Bill $bill)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can void bills.'], 403);
        }

        if ($bill->status === BillStatus::Void || $bill->status === BillStatus::Paid) {
            return response()->json(['message' => 'Cannot void a bill that is already paid or voided.'], 400);
        }

        return DB::transaction(function () use ($bill) {
            // 1. Request Xero VOID
            if ($bill->xero_invoice_id) {
                $this->xeroBillService->voidPurchaseInvoice($bill);
            }

            // 2. Restore Balance
            if ($bill->status === BillStatus::Approved) {
                $expendable = $bill->expendable;
                $expendable->balance += $bill->amount;
                $expendable->save();
            }

            // 3. Update Status
            $bill->status = BillStatus::Void;
            $bill->save();

            // 4. Mark linked transactions as cancelled (if any)
            $bill->transactions()->update(['is_paid' => false]); // Or maybe delete them?

            return response()->json($bill->fresh(['contractor', 'expendable', 'transactionType']));
        });

    }

    public function all(Request $request)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Bill::with(['project', 'contractor', 'expendable', 'transactionType', 'approvalInstance.steps']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function pendingCounts()
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['bills' => 0, 'invoices' => 0]);
        }

        return response()->json([
            'bills' => Bill::where('status', BillStatus::PendingApproval)->count(),
            'invoices' => \App\Models\Invoice::where('status', 'pending_approval')->count(),
        ]);
    }

    private function initializeBillApprovalInstance(Bill $bill, int $projectId): void
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

    private function approveThroughFlow($user, Bill $bill, ApprovalInstance $instance)
    {
        if (!in_array($instance->status, ['pending', 'in_progress'], true)) {
            return response()->json(['message' => 'Approval instance is not active for this bill.'], 400);
        }

        $currentStep = $instance->steps
            ->where('status', 'pending')
            ->sortBy('step_order')
            ->first();

        if (! $currentStep) {
            $this->performFinalBillApproval($bill);
            $instance->status = 'completed';
            $instance->save();

            return response()->json($bill->fresh(['contractor', 'expendable', 'transactionType', 'approvalInstance.steps']));
        }

        if (! $this->canUserApproveStep($user, $currentStep)) {
            return response()->json(['message' => 'You are not assigned to the current approval stage.'], 403);
        }

        DB::transaction(function () use ($instance, $currentStep, $user, $bill) {
            $currentStep->status = 'approved';
            $currentStep->acted_by_user_id = $user->id;
            $currentStep->acted_at = now();
            $currentStep->save();

            $nextStep = $instance->steps()
                ->where('status', 'pending')
                ->orderBy('step_order')
                ->first();

            if ($nextStep) {
                $instance->current_step_order = $nextStep->step_order;
                $instance->status = 'in_progress';
                $instance->save();

                return;
            }

            $instance->current_step_order = null;
            $instance->status = 'completed';
            $instance->save();

            $this->performFinalBillApproval($bill);
        });

        return response()->json($bill->fresh(['contractor', 'expendable', 'transactionType', 'approvalInstance.steps']));
    }

    private function canUserApproveStep($user, $step): bool
    {
        if ($step->approver_type === 'user') {
            return (int) $step->approver_user_id === (int) $user->id;
        }

        if ($step->approver_type === 'role') {
            return (int) $step->approver_role_id === (int) $user->role_id;
        }

        return false;
    }

    private function performFinalBillApproval(Bill $bill): void
    {
        DB::transaction(function () use ($bill) {
            $expendable = $bill->expendable;

            if ($bill->amount > $expendable->balance) {
                throw new RuntimeException("Bill amount exceeds remaining balance ({$expendable->balance}).");
            }

            $xeroInvoiceId = $this->xeroBillService->createPurchaseInvoice($bill);
            $bill->xero_invoice_id = $xeroInvoiceId;
            $bill->status = BillStatus::Approved;
            $bill->save();

            $expendable->balance -= $bill->amount;
            $expendable->save();

            $this->xeroAttachmentService->uploadAttachments($bill, $xeroInvoiceId);
        });
    }
}
