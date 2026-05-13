<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Project;
use App\Models\ProjectExpendable;
use App\Enums\BillStatus;
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
            ->with(['contractor', 'expendable', 'transactionType'])
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
        ]);

        $expendable = ProjectExpendable::findOrFail($validated['project_expendable_id']);

        if ($validated['amount'] > $expendable->balance) {
            return response()->json([
                'message' => 'Bill amount exceeds the remaining balance of the contract.',
                'errors' => [
                    'amount' => ["Remaining balance is {$expendable->balance}."]
                ]
            ], 422);
        }

        $bill = Bill::create([
            'project_id' => $project->id,
            'contractor_id' => $validated['contractor_id'],
            'project_expendable_id' => $validated['project_expendable_id'],
            'transaction_type_id' => $validated['transaction_type_id'],
            'amount' => $validated['amount'],
            'status' => BillStatus::PendingApproval,
        ]);

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json($bill, 201);
    }

    public function approve(Project $project, Bill $bill)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can approve bills.'], 403);
        }

        if ($bill->status !== BillStatus::PendingApproval) {
            return response()->json(['message' => 'Bill is not in pending status.'], 400);
        }

        return DB::transaction(function () use ($bill) {
            $expendable = $bill->expendable;

            // Re-validate balance just in case
            if ($bill->amount > $expendable->balance) {
                throw new RuntimeException("Bill amount exceeds remaining balance ({$expendable->balance}).");
            }

            // 1. Push to Xero
            $xeroInvoiceId = $this->xeroBillService->createPurchaseInvoice($bill);
            $bill->xero_invoice_id = $xeroInvoiceId;
            $bill->status = BillStatus::Approved;
            $bill->save();

            // 2. Update Expendable Balance
            $expendable->balance -= $bill->amount;
            $expendable->save();

            // 3. Push Attachments to Xero
            $this->xeroAttachmentService->uploadAttachments($bill, $xeroInvoiceId);

            return response()->json($bill->fresh(['contractor', 'expendable', 'transactionType']));
        });
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

        $query = Bill::with(['project', 'contractor', 'expendable', 'transactionType']);

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
}
