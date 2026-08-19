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
use App\Services\CurrencyConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BillController extends Controller
{
    use HasProjectPermissions;

    private const ALLOWED_XERO_TAX_TYPES = [
        'INPUT',
        'OUTPUT',
        'EXEMPTOUTPUT',
        'INPUTTAXED',
        'BASEXCLUDED',
        'EXEMPTEXPENSES',
        // Legacy internal value kept for backward compatibility.
        'NONE',
    ];

    public function __construct(
        private readonly XeroBillService $xeroBillService,
        private readonly XeroAttachmentService $xeroAttachmentService,
        private readonly CurrencyConversionService $currencyConversionService
    ) {}

    public function index(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $bills = Bill::where('project_id', $project->id)
            ->with(['contractor', 'expendable', 'transactionType', 'approvalInstance.steps', 'files'])
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

        $isGeneralExpense = $request->input('bill_type') === 'general_expense';

        $rules = [
            'bill_type' => 'nullable|string|in:contractor_bill,general_expense',
            'document' => 'nullable|file|mimes:pdf|max:10240',
            'xero_account_code' => 'required|string|max:50',
            'xero_tax_type' => 'nullable|string|in:' . implode(',', self::ALLOWED_XERO_TAX_TYPES),
            'reference_number' => 'required|string|max:255',
            'due_date' => 'nullable|date',
            'currency' => 'required|string|max:3',
            'amount' => 'required|numeric|min:0.01',
        ];

        if ($isGeneralExpense) {
            $rules['transaction_type_id'] = 'nullable|exists:transaction_types,id';
            $rules['contractor_id'] = 'required|exists:users,id';
        } else {
            $rules['contractor_id'] = 'required|exists:users,id';
            $rules['project_expendable_id'] = 'required|exists:project_expendables,id';
            $rules['transaction_type_id'] = 'required|exists:transaction_types,id';
            $rules['payment_details'] = 'required|array';
            $rules['payment_details.payment_method'] = 'required|string|max:50';
            $rules['payment_details.account_name'] = 'required_if:payment_details.payment_method,bank_local,bank_wire|nullable|string|max:255';
            $rules['payment_details.account_number'] = 'required_if:payment_details.payment_method,bank_local,bank_wire|nullable|string|max:255';
            $rules['payment_details.bank_name'] = 'nullable|string|max:255';
            $rules['payment_details.bsb'] = 'required_if:payment_details.payment_method,bank_local|nullable|string|max:255';
            $rules['payment_details.swift_code'] = 'required_if:payment_details.payment_method,bank_wire|nullable|string|max:255';
            $rules['payment_details.iban'] = 'nullable|string|max:255';
            $rules['payment_details.paypal_email'] = 'required_if:payment_details.payment_method,paypal|nullable|email|max:255';
            $rules['payment_details.payoneer_email'] = 'required_if:payment_details.payment_method,payoneer|nullable|email|max:255';
            $rules['payment_details.wise_email'] = 'required_if:payment_details.payment_method,wise|nullable|email|max:255';
            $rules['payment_details.wallet_address'] = 'required_if:payment_details.payment_method,crypto|nullable|string|max:255';
            $rules['payment_details.coin_type'] = 'required_if:payment_details.payment_method,crypto|nullable|string|max:50';
            $rules['payment_details.notes'] = 'nullable|string|max:1000';
        }

        $validated = $request->validate($rules);

        if (!$isGeneralExpense) {
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

            $billCurrency = $validated['currency'] ?? 'AUD';
            $expendableCurrency = $expendable->currency ?? 'AUD';

            try {
                $amountInExpendableCurrency = $this->currencyConversionService->convert(
                    $validated['amount'],
                    $billCurrency,
                    $expendableCurrency
                );
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Failed to convert currency: ' . $e->getMessage(),
                    'errors' => ['currency' => [$e->getMessage()]],
                ], 422);
            }

            $pendingBills = Bill::where('project_expendable_id', $expendable->id)
                ->where('status', BillStatus::PendingApproval)
                ->get();

            $pendingAmountSumInExpendableCurrency = 0;
            foreach ($pendingBills as $pendingBill) {
                $pendingBillCurrency = $pendingBill->currency ?? 'AUD';
                try {
                    $pendingAmountSumInExpendableCurrency += $this->currencyConversionService->convert(
                        $pendingBill->amount,
                        $pendingBillCurrency,
                        $expendableCurrency
                    );
                } catch (\Exception $e) {
                    // Ignore conversion failure for existing pending bills
                }
            }

            if (round(($pendingAmountSumInExpendableCurrency + $amountInExpendableCurrency), 2) > round($expendable->balance, 2)) {
                $availableForNewBills = max(0, $expendable->balance - $pendingAmountSumInExpendableCurrency);
                return response()->json([
                    'message' => 'Bill amount exceeds the remaining balance of the contract.',
                    'errors' => [
                        'amount' => ["Remaining balance available for new bills is {$availableForNewBills} {$expendableCurrency} (accounting for pending bills)."]
                    ]
                ], 422);
            }
        }

        $bill = DB::transaction(function () use ($project, $validated, $isGeneralExpense) {
            $bill = Bill::create([
                'project_id' => $project->id,
                'contractor_id' => $isGeneralExpense ? ($validated['contractor_id'] ?? null) : $validated['contractor_id'],
                'project_expendable_id' => $isGeneralExpense ? null : $validated['project_expendable_id'],
                'transaction_type_id' => $validated['transaction_type_id'] ?? null,
                'xero_account_code' => $validated['xero_account_code'] ?? null,
                'xero_tax_type' => $validated['xero_tax_type'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'currency' => $validated['currency'] ?? 'AUD',
                'amount' => $validated['amount'],
                'status' => BillStatus::PendingApproval,
            ]);

            if (!$isGeneralExpense) {
                $paymentDetails = $validated['payment_details'];

                $bill->paymentDetail()->create([
                    'contractor_id' => $validated['contractor_id'],
                    'payment_method' => $paymentDetails['payment_method'],
                    'details' => [
                        'account_name' => $paymentDetails['account_name'] ?? null,
                        'account_number' => $paymentDetails['account_number'] ?? null,
                        'bank_name' => $paymentDetails['bank_name'] ?? null,
                        'bsb' => $paymentDetails['bsb'] ?? null,
                        'swift_code' => $paymentDetails['swift_code'] ?? null,
                        'iban' => $paymentDetails['iban'] ?? null,
                        'notes' => $paymentDetails['notes'] ?? null,
                    ],
                ]);
            }

            return $bill;
        });

        $this->initializeBillApprovalInstance($bill, $project->id);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $objectPath = \Illuminate\Support\Facades\Storage::disk('gcs')->putFile('bills', $file);

            $bill->files()->create([
                'project_id' => $project->id,
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'path' => $objectPath,
            ]);
        }

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json($bill->fresh(['paymentDetail']), 201);
    }

    public function update(Request $request, Project $project, Bill $bill)
    {
        $user = Auth::user();

        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ((int) $bill->project_id !== (int) $project->id) {
            return response()->json(['message' => 'Bill does not belong to this project.'], 404);
        }

        if (! $user->hasPermission('edit_project_bills') && ! $user->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($bill->status !== BillStatus::PendingApproval) {
            if ($bill->status === BillStatus::Approved) {
                return response()->json(['message' => 'Synced bills cannot be edited, they can only be voided.'], 400);
            }
            if ($bill->status === BillStatus::Paid || $bill->status === BillStatus::PartialPaid) {
                return response()->json(['message' => 'Paid bills cannot be edited.'], 400);
            }
            return response()->json(['message' => 'Only pending approval bills can be edited.'], 400);
        }

        $isGeneralExpense = $bill->project_expendable_id === null;

        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'xero_account_code' => 'required|string|max:50',
            'xero_tax_type' => 'nullable|string|in:' . implode(',', self::ALLOWED_XERO_TAX_TYPES),
            'reference_number' => 'required|string|max:255',
            'due_date' => 'nullable|date',
            'currency' => 'required|string|max:3',
            // Settable here because bills uploaded by suppliers through a public share
            // link arrive without one - the guest has no way to know it. Without this
            // rule the field could never be populated after creation.
            'transaction_type_id' => 'nullable|exists:transaction_types,id',
        ];

        if (!$isGeneralExpense) {
            $rules['payment_details'] = 'required|array';
            $rules['payment_details.payment_method'] = 'required|string|max:50';
            $rules['payment_details.account_name'] = 'required_if:payment_details.payment_method,bank_local,bank_wire|nullable|string|max:255';
            $rules['payment_details.account_number'] = 'required_if:payment_details.payment_method,bank_local,bank_wire|nullable|string|max:255';
            $rules['payment_details.bank_name'] = 'nullable|string|max:255';
            $rules['payment_details.bsb'] = 'required_if:payment_details.payment_method,bank_local|nullable|string|max:255';
            $rules['payment_details.swift_code'] = 'required_if:payment_details.payment_method,bank_wire|nullable|string|max:255';
            $rules['payment_details.iban'] = 'nullable|string|max:255';
            $rules['payment_details.paypal_email'] = 'required_if:payment_details.payment_method,paypal|nullable|email|max:255';
            $rules['payment_details.payoneer_email'] = 'required_if:payment_details.payment_method,payoneer|nullable|email|max:255';
            $rules['payment_details.wise_email'] = 'required_if:payment_details.payment_method,wise|nullable|email|max:255';
            $rules['payment_details.wallet_address'] = 'required_if:payment_details.payment_method,crypto|nullable|string|max:255';
            $rules['payment_details.coin_type'] = 'required_if:payment_details.payment_method,crypto|nullable|string|max:50';
            $rules['payment_details.notes'] = 'nullable|string|max:1000';
        }

        $validated = $request->validate($rules);

        if (!$isGeneralExpense) {
            $expendable = $bill->expendable;
            $billCurrency = $validated['currency'] ?? 'AUD';
            $expendableCurrency = $expendable->currency ?? 'AUD';

            try {
                $amountInExpendableCurrency = $this->currencyConversionService->convert(
                    $validated['amount'],
                    $billCurrency,
                    $expendableCurrency
                );
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Failed to convert currency: ' . $e->getMessage(),
                    'errors' => ['currency' => [$e->getMessage()]],
                ], 422);
            }

            $pendingBills = Bill::where('project_expendable_id', $expendable->id)
                ->where('status', BillStatus::PendingApproval)
                ->where('id', '!=', $bill->id)
                ->get();

            $pendingAmountSumInExpendableCurrency = 0;
            foreach ($pendingBills as $pendingBill) {
                $pendingBillCurrency = $pendingBill->currency ?? 'AUD';
                try {
                    $pendingAmountSumInExpendableCurrency += $this->currencyConversionService->convert(
                        $pendingBill->amount,
                        $pendingBillCurrency,
                        $expendableCurrency
                    );
                } catch (\Exception $e) {
                    // Ignore
                }
            }

            if (round(($pendingAmountSumInExpendableCurrency + $amountInExpendableCurrency), 2) > round($expendable->balance, 2)) {
                $availableForNewBills = max(0, $expendable->balance - $pendingAmountSumInExpendableCurrency);
                return response()->json([
                    'message' => 'Bill amount exceeds the remaining balance of the contract.',
                    'errors' => [
                        'amount' => ["Remaining balance available for bills is {$availableForNewBills} {$expendableCurrency}."]
                    ]
                ], 422);
            }
        }

        DB::transaction(function () use ($bill, $validated, $isGeneralExpense) {
            $bill->fill([
                'amount' => $validated['amount'],
                'xero_account_code' => $validated['xero_account_code'] ?? null,
                'xero_tax_type' => $validated['xero_tax_type'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'currency' => $validated['currency'] ?? 'AUD',
            ]);

            // Only overwrite when the field was actually sent, so existing callers that
            // omit it (BillDetails.vue) don't blank a transaction type that was set.
            if (array_key_exists('transaction_type_id', $validated)) {
                $bill->transaction_type_id = $validated['transaction_type_id'];
            }

            $bill->save();

            if (!$isGeneralExpense && isset($validated['payment_details'])) {
                $paymentDetails = $validated['payment_details'];

                $bill->paymentDetail()->updateOrCreate(
                    ['bill_id' => $bill->id],
                    [
                        'contractor_id' => $bill->contractor_id,
                        'payment_method' => $paymentDetails['payment_method'],
                        'details' => [
                            // Merged over what is already stored, so identifiers this
                            // form doesn't render (IFSC, sort code, routing number, and
                            // the PayPal/Wise/Payoneer/crypto destinations that arrive
                            // on supplier-submitted bills) survive an admin edit.
                            ...array_diff_key(
                                (array) ($bill->paymentDetail?->details ?? []),
                                array_flip(['account_name', 'account_number', 'bank_name', 'bsb', 'swift_code', 'iban', 'notes'])
                            ),
                            'account_name' => $paymentDetails['account_name'] ?? null,
                            'account_number' => $paymentDetails['account_number'] ?? null,
                            'bank_name' => $paymentDetails['bank_name'] ?? null,
                            'bsb' => $paymentDetails['bsb'] ?? null,
                            'swift_code' => $paymentDetails['swift_code'] ?? null,
                            'iban' => $paymentDetails['iban'] ?? null,
                            'notes' => $paymentDetails['notes'] ?? null,
                        ],
                    ]
                );
            }
        });

        return response()->json($bill->fresh(['contractor', 'expendable', 'transactionType', 'paymentDetail', 'approvalInstance.steps']));
    }

    public function approve(Bill $bill)
    {
        $user = Auth::user();

        $project = $bill->project;
        if (! $project || ! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($bill->status !== BillStatus::PendingApproval) {
            return response()->json(['message' => 'Bill is not in pending status.'], 400);
        }

        $approvalInput = request()->validate([
            'xero_account_code' => 'nullable|string|max:50',
            'xero_tax_type' => 'nullable|string|in:' . implode(',', self::ALLOWED_XERO_TAX_TYPES),
        ]);

        if (array_key_exists('xero_account_code', $approvalInput) && !empty($approvalInput['xero_account_code'])) {
            $bill->xero_account_code = $approvalInput['xero_account_code'];
        }

        if (array_key_exists('xero_tax_type', $approvalInput) && !empty($approvalInput['xero_tax_type'])) {
            $bill->xero_tax_type = $approvalInput['xero_tax_type'];
        }

        if ($bill->isDirty(['xero_account_code', 'xero_tax_type'])) {
            $bill->save();
        }

        try {
            $instance = $bill->approvalInstance()->with('steps')->first();

            if ($instance) {
                return $this->approveThroughFlow($user, $bill, $instance);
            }

            if (!$user->isSuperAdmin()) {
                return response()->json(['message' => 'Only Super Admins can approve bills.'], 403);
            }

            $this->performFinalBillApproval($bill);

            return response()->json($bill->fresh(['contractor', 'expendable', 'transactionType', 'approvalInstance.steps']));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return response()->json(['message' => 'Approval failed while syncing with Xero.'], 500);
        }
    }

    public function void(Bill $bill)
    {
        $user = Auth::user();
        $project = $bill->project;
        if (! $project || ! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

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

                $billCurrency = $bill->currency ?? 'AUD';
                $expendableCurrency = $expendable->currency ?? 'AUD';

                try {
                    $amountInExpendableCurrency = $this->currencyConversionService->convert(
                        $bill->amount,
                        $billCurrency,
                        $expendableCurrency
                    );
                    $expendable->balance += $amountInExpendableCurrency;
                    $expendable->save();
                } catch (\Exception $e) {
                    throw new RuntimeException("Failed to restore balance: " . $e->getMessage());
                }
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

        $query = Bill::withTrashed()->with([
            'project', 'contractor', 'expendable', 'transactionType', 'paymentDetail', 'files',
            'transactions.user', 'transactions.transactionType',
            'approvalInstance' => fn($q) => $q->with([
                'steps.approverRole:id,name',
                'steps.approverUser:id,name',
                'steps.actedBy:id,name',
            ]),
        ]);

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'deleted') {
                $query->onlyTrashed();
            } else {
                $query->where('status', $request->status)->whereNull('deleted_at');
            }
        } else {
            $query->whereNull('deleted_at');
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                // If it's a numeric search, it might be an amount
                if (is_numeric($search)) {
                    $q->where('amount', 'like', "%{$search}%");
                }
                
                $q->orWhereHas('contractor', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%");
                })->orWhereHas('project', function ($q3) use ($search) {
                    $q3->where('name', 'like', "%{$search}%");
                })->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        $paginated = $query->latest()->paginate(20);

        $pendingCount = Bill::where('status', \App\Enums\BillStatus::PendingApproval)->whereNull('deleted_at')->count();
        $approvedCount = Bill::where('status', \App\Enums\BillStatus::Approved)->whereNull('deleted_at')->count();
        $paidCount = Bill::whereIn('status', [\App\Enums\BillStatus::Paid, \App\Enums\BillStatus::PartialPaid])->whereNull('deleted_at')->count();
        $voidCount = Bill::where('status', \App\Enums\BillStatus::Void)->whereNull('deleted_at')->count();
        $deletedCount = Bill::onlyTrashed()->count();

        return response()->json([
            'pagination' => $paginated,
            'counts' => [
                'pending_approval' => $pendingCount,
                'approved' => $approvedCount,
                'paid' => $paidCount,
                'void' => $voidCount,
                'deleted' => $deletedCount,
                'total' => $pendingCount + $approvedCount + $paidCount + $voidCount,
            ]
        ]);
    }

    public function uploadAttachment(Request $request, Bill $bill)
    {
        $user = Auth::user();
        if (! $this->canAccessProject($user, $bill->project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $user->isSuperAdmin() && ! $user->hasPermission('edit_project_bills')) {
            return response()->json(['message' => 'Only authorized users can upload attachments to bills.'], 403);
        }

        $request->validate([
            'document' => 'required|file|mimes:pdf|max:10240',
        ]);

        $file = $request->file('document');
        $objectPath = \Illuminate\Support\Facades\Storage::disk('gcs')->putFile('bills', $file);

        $attachment = $bill->files()->create([
            'project_id' => $bill->project_id,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'path' => $objectPath,
        ]);

        return response()->json([
            'message' => 'Attachment uploaded successfully.',
            'attachment' => $attachment
        ]);
    }

    public function destroy(Bill $bill)
    {
        $user = Auth::user();
        if (! $this->canAccessProject($user, $bill->project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $user->isSuperAdmin() && ! $user->hasPermission('delete_project_bills')) {
            return response()->json(['message' => 'Only authorized users can delete bills.'], 403);
        }

        if ($bill->status !== BillStatus::PendingApproval) {
            return response()->json(['message' => 'Only pending bills can be deleted. Approved bills must be voided.'], 400);
        }

        $bill->delete();

        return response()->json(['message' => 'Bill deleted successfully.']);
    }

    public function restore($id)
    {
        $user = Auth::user();
        $bill = Bill::withTrashed()->findOrFail($id);
        
        if (! $this->canAccessProject($user, $bill->project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $user->isSuperAdmin() && ! $user->hasPermission('restore_project_bills')) {
            return response()->json(['message' => 'Only authorized users can restore bills.'], 403);
        }

        $bill->restore();

        return response()->json(['message' => 'Bill restored successfully.']);
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

    public function show(Project $project, Bill $bill)
    {
        $user = Auth::user();

        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ((int) $bill->project_id !== (int) $project->id) {
            return response()->json(['message' => 'Bill does not belong to this project.'], 404);
        }

        return response()->json($bill->load([
            'project', 'contractor', 'expendable', 'transactionType', 'paymentDetail',
            'approvalInstance.steps.approverRole:id,name',
            'approvalInstance.steps.approverUser:id,name',
            'approvalInstance.steps.actedBy:id,name',
        ]));
    }

    private function initializeBillApprovalInstance(Bill $bill, int $projectId): void
    {
        // Shared with the public guest bill-upload endpoint
        // (PublicProjectController::storeBill) so both paths enter the same flow.
        app(\App\Services\BillApprovalFlowService::class)->initialize($bill, $projectId);
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

            $resolvedAccountCode = $bill->xero_account_code ?: $bill->transactionType?->xero_account_code;
            if (! $resolvedAccountCode) {
                throw new RuntimeException('Xero account code is required before bill approval. Please set it on the bills page.');
            }

            if (! $bill->xero_tax_type) {
                $bill->xero_tax_type = 'INPUT';
            }

            $bill->xero_account_code = $resolvedAccountCode;
            $bill->save();

            if ($expendable) {
                $billCurrency = $bill->currency ?? 'AUD';
                $expendableCurrency = $expendable->currency ?? 'AUD';

                try {
                    $amountInExpendableCurrency = $this->currencyConversionService->convert(
                        $bill->amount,
                        $billCurrency,
                        $expendableCurrency
                    );
                } catch (\Exception $e) {
                    throw new RuntimeException("Currency conversion failed: " . $e->getMessage());
                }

                if ($amountInExpendableCurrency > $expendable->balance) {
                    throw new RuntimeException("Bill amount exceeds remaining balance ({$expendable->balance} {$expendableCurrency}).");
                }
            }

            $xeroInvoiceId = $this->xeroBillService->createPurchaseInvoice($bill);
            $bill->xero_invoice_id = $xeroInvoiceId;
            $bill->status = BillStatus::Approved;
            $bill->save();

            if ($expendable) {
                $expendable->balance -= $amountInExpendableCurrency;
                $expendable->save();
            }

            $this->xeroAttachmentService->uploadAttachments($bill, $xeroInvoiceId);
        });
    }
}
