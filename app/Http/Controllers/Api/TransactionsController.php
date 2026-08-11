<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Services\AirwallexService;
use App\Services\StripePayoutService;
use App\Services\XeroInvoiceService;

class TransactionsController extends Controller // Assuming your controller is named TransactionController
{
    public function outstandingDocs(Request $request)
    {
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('view_project_transactions')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $bills = \App\Models\Bill::whereIn('status', [\App\Enums\BillStatus::Approved, \App\Enums\BillStatus::PartialPaid])
            ->with(['project:id,name', 'contractor:id,name'])
            ->get();

        $invoices = \App\Models\Invoice::whereIn('status', ['authorised', 'sent', 'partial_paid'])
            ->with(['project:id,name', 'client:id,name'])
            ->get();

        return response()->json([
            'bills' => $bills,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Add a single transaction (income or expense) to a project.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addTransactions(Request $request, Project $project)
    {
        // Authorize the action using Laravel's Gate facade
        // Ensure the authenticated user has permission to add transactions to this project.
        $this->authorize('manageTransactions', $project);

        // Define validation rules for a single transaction object
        // The request body is expected to contain description, amount, type, etc., directly.
        $validationRules = [
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'user_id' => 'required_if:type,expense,bonus|nullable|exists:users,id', // User ID is required when type is expense or bonus
            'client_id' => 'required_if:type,income|nullable|exists:clients,id', // Client ID is required for income
            'currency' => 'required|string',
            'hours_spent' => 'nullable|numeric|min:0', // Hours spent is optional
            'type' => 'required|in:income,expense,bonus', // Type must be 'income', 'expense', or 'bonus'
            'transaction_type_id' => 'required|exists:transaction_types,id', // Always required
            'bill_id' => 'nullable|exists:bills,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'bank_transaction_id' => 'nullable|string|max:255',
            'conversion_rate' => 'nullable|numeric|min:0.000001',
        ];

        // Validate the incoming request data against the defined rules
        // If validation fails, Laravel automatically sends a 422 Unprocessable Entity response.
        $validated = $request->validate($validationRules);

        // --- VALIDATION: BILL REMAINING AMOUNT ---
        if (!empty($validated['bill_id'])) {
            $bill = \App\Models\Bill::find($validated['bill_id']);
            if ($bill) {
                $remainingAmount = $this->getBillRemainingAmount($bill);
                $conversionService = app(\App\Services\CurrencyConversionService::class);
                try {
                    $convertedNewAmount = $conversionService->convert(
                        (float) $validated['amount'],
                        $validated['currency'],
                        $bill->currency ?? 'AUD'
                    );
                } catch (\Exception $e) {
                    $convertedNewAmount = (float) $validated['amount'];
                }

                if (round($convertedNewAmount, 2) > round($remainingAmount, 2)) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => ['amount' => ['The transaction amount exceeds the remaining unpaid amount of the bill.']]
                    ], 422);
                }
            }
        }

        // --- VALIDATION: BANK TRANSACTION REMAINING AMOUNT ---
        if (!empty($validated['bank_transaction_id'])) {
            $airwallexService = app(\App\Services\AirwallexService::class);
            try {
                $bankData = $this->getBankTransactionData($validated['bank_transaction_id'], $airwallexService);

                $conversionService = app(\App\Services\CurrencyConversionService::class);
                try {
                    if (!empty($validated['conversion_rate']) && $validated['conversion_rate'] > 0) {
                        $convertedNewAmount = (float) $validated['amount'] / (float) $validated['conversion_rate'];
                    } else {
                        $convertedNewAmount = $conversionService->convert(
                            (float) $validated['amount'],
                            $validated['currency'],
                            $bankData['currency'] ?? 'AUD'
                        );
                    }
                } catch (\Exception $e) {
                    $convertedNewAmount = (float) $validated['amount'];
                }

                if (round($convertedNewAmount, 2) > round($bankData['remaining_amount'], 2)) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => ['amount' => ['The transaction amount exceeds the remaining unlinked amount of the bank transaction.']]
                    ], 422);
                }
            } catch (\Exception $e) {
                \Log::warning("Could not validate bank transaction amount: " . $e->getMessage());
            }
        }

        // Create a single transaction record in the database
        // The create method on the relationship automatically sets the project_id.
        $transaction = $project->transactions()->create([
            'description' => $validated['description'] ?? ($validated['type'] === 'income' ? 'Income Payment' : 'Expense Payment'),
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'user_id' => $validated['user_id'] ?? null, // Use null if user_id is not provided
            'client_id' => $validated['client_id'] ?? null, // Store client_id if provided
            'hours_spent' => $validated['hours_spent'] ?? null, // Use null if hours_spent is not provided
            'type' => $validated['type'],
            'transaction_type_id' => $validated['transaction_type_id'] ?? null,
            'bill_id' => $validated['bill_id'] ?? null,
            'invoice_id' => $validated['invoice_id'] ?? null,
            'bank_transaction_id' => $validated['bank_transaction_id'] ?? null,
            'exchange_rate' => $validated['conversion_rate'] ?? null,
            'is_paid' => !empty($validated['bill_id']) || !empty($validated['invoice_id']),
            'payment_date' => (!empty($validated['bill_id']) || !empty($validated['invoice_id'])) ? now() : null,
        ]);

        if ($transaction->bill_id) {
            try {
                $airwallexService = app(\App\Services\AirwallexService::class);
                $xeroBillService = app(\App\Services\XeroBillService::class);
                $this->syncLinkedBillPayment($transaction, $airwallexService, $xeroBillService);
            } catch (\Exception $e) {
                \Log::error("Failed to sync Xero payment after adding transaction: " . $e->getMessage());
            }
            $transaction->bill->recalculateStatus();
        }

        if ($transaction->invoice_id) {
            $transaction->invoice->recalculateStatus();
        }

        $transaction->load(['bill.contractor', 'bill.project', 'bill.transactions', 'invoice.client', 'invoice.project', 'invoice.transactions', 'project']);

        // Return the newly created transaction as a JSON response with a 201 Created status
        return response()->json($transaction, 201);
    }

    /**
     * Handle payment processing for a specific transaction.
     * This function manages both full and partial payments.
     *
     * @param  Project  $project  The project the transaction belongs to.
     * @param  Transaction  $transaction  The specific transaction being paid.
     * @return \Illuminate\Http\JsonResponse
     */
    public function processPayment(Request $request, Project $project, Transaction $transaction)
    {
        // 1. Authorization Check
        // Ensure the authenticated user has permission to manage project expenses/income.
        // Replace 'manageTransactions' with your actual policy method if different.
        $this->authorize('manageTransactions', $project);

        // Ensure the transaction belongs to the specified project
        if ($transaction->project_id !== $project->id) {
            return response()->json(['message' => 'Transaction not found in this project.'], 404);
        }

        // 2. Validation
        $validated = $request->validate([
            'payment_amount' => 'required|numeric|min:0', // Min can be 0 if pay_in_full is true for 0-amount transactions, but usually min:0.01 for actual payments
            'pay_in_full' => 'required|boolean',
            'payment_date' => 'nullable|date', // Added validation for payment_date
        ]);

        $paymentAmount = (float) $validated['payment_amount'];
        $payInFullRequest = (bool) $validated['pay_in_full']; // Renamed to avoid confusion with internal logic
        $paymentDate = $validated['payment_date'] ?? null; // Get payment date, default to null if not provided

        $originalTransactionAmount = (float) $transaction->amount;

        // Determine if it's truly a full payment, considering floating point precision.
        // If payInFullRequest is true OR the payment amount is very close to the original transaction amount,
        // treat it as a full payment.
        $isActualFullPayment = $payInFullRequest || (abs($paymentAmount - $originalTransactionAmount) < 0.01); // Use a small tolerance for comparison

        // Backend validation: If not a full payment, payment amount cannot exceed the remaining balance.
        // This check is only relevant for partial payments.
        if (! $isActualFullPayment && $paymentAmount > $originalTransactionAmount) {
            return response()->json([
                'errors' => ['payment_amount' => ['Payment amount cannot exceed the remaining balance of the original transaction.']],
                'message' => 'The given data was invalid.',
            ], 422);
        }

        // Use a database transaction to ensure atomicity
        DB::beginTransaction();

        try {
            if ($isActualFullPayment) {
                // 3. Handle Full Payment
                // Mark the original transaction as fully paid and set its amount to 0 (if it represents remaining balance)
                $transaction->update([
                    'is_paid' => true,
                    'payment_date' => $paymentDate, // Store payment date for full payment
                ]);
            } else {
                // 4. Handle Partial Payment

                // Calculate the new remaining amount for the original transaction
                $updatedRemainingAmount = $originalTransactionAmount - $paymentAmount;

                // Update the original transaction's remaining amount
                $transaction->update([
                    'amount' => $updatedRemainingAmount,
                    // Do NOT set is_paid to true here, as it's a partial payment
                ]);

                // Create a new transaction record specifically for the paid portion
                $project->transactions()->create([
                    'description' => $transaction->description.' (Partial '.ucfirst($transaction->type).')', // More descriptive
                    'amount' => $paymentAmount,
                    'currency' => $transaction->currency, // Use parent transaction's currency
                    'user_id' => $transaction->user_id, // Assign to the same user if applicable
                    'type' => $transaction->type, // Keep the same type (income/expense/bonus)
                    'is_paid' => true, // This new record represents the paid portion
                    'transaction_id' => $transaction->id, // Link to the original (parent) transaction
                    'payment_date' => $paymentDate, // Store payment date for this partial payment record
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Payment processed successfully.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Failed to process payment.', 'error' => $e->getMessage()], 500);
        }
    }

    public function all(Request $request)
    {
        $query = Transaction::with([
            'project',
            'user',
            'client',
            'transactionType',
            'bill.contractor',
            'bill.project',
            'bill.transactions',
            'invoice.client',
            'invoice.project',
            'invoice.transactions',
            'files'
        ]);

        if ($request->status === 'deleted') {
            $query->onlyTrashed();
        } else {
            if ($request->filled('status') && $request->status !== 'all') {
                $isPaid = $request->status === 'paid';
                $query->where('is_paid', $isPaid);
            }
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('amount', 'like', "%{$search}%")
                  ->orWhere('bank_transaction_id', 'like', "%{$search}%")
                  ->orWhereHas('project', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('client', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $query->orderBy('created_at', 'desc');

        return response()->json($query->paginate(20));
    }

    /**
     * Helper to sync a linked bill payment to Xero.
     */
    private function syncLinkedBillPayment(Transaction $transaction, AirwallexService $airwallexService, \App\Services\XeroBillService $xeroBillService): ?string
    {
//        \Log::info("syncLinkedBillPayment started for transaction {$transaction->id}");
        $bill = $transaction->bill;
        if (!$bill || !$bill->xero_invoice_id) {
            \Log::info("syncLinkedBillPayment early return: No bill or xero_invoice_id for transaction {$transaction->id}");
            return null;
        }

        $airwallexData = [];
        if ($transaction->bank_transaction_id) {
            try {
                $airwallexData = $airwallexService->getTransaction($transaction->bank_transaction_id);

                // If it is a PAYOUT, search for related conversions to consolidate
                if (($airwallexData['transaction_type'] ?? '') === 'PAYOUT') {
                    $payoutTime = strtotime($airwallexData['created_at']);
                    $nearTxs = $airwallexService->getTransactions([
                        'from_created_at' => date('Y-m-d', $payoutTime - 86400),
                        'page_size' => 50
                    ]);
                    $nearItems = $nearTxs['items'] ?? [];

                    $buy = null;
                    $sell = null;
                    foreach ($nearItems as $item) {
                        if (($item['transaction_type'] ?? '') === 'CONVERSION_BUY') {
                            $buyCurrency = $item['currency'] ?? '';
                            $buyAmount = abs((float)($item['amount'] ?? 0));
                            $payoutCurrency = $airwallexData['currency'] ?? '';
                            $payoutAmount = abs((float)($airwallexData['amount'] ?? 0));
                            $buyTime = strtotime($item['created_at']);

                            if ($buyCurrency === $payoutCurrency &&
                                abs($buyAmount - $payoutAmount) < 0.01 &&
                                abs($payoutTime - $buyTime) <= 60) {
                                $buy = $item;
                                break;
                            }
                        }
                    }

                    if ($buy) {
                        foreach ($nearItems as $item) {
                            if (($item['transaction_type'] ?? '') === 'CONVERSION_SELL' && ($item['source_id'] ?? '') === ($buy['source_id'] ?? '')) {
                                $sell = $item;
                                break;
                            }
                        }
                    }

                    if ($buy && $sell) {
                        $airwallexData['is_consolidated'] = true;
                        $airwallexData['funding_amount'] = abs((float)($sell['amount'] ?? 0));
                        $airwallexData['funding_currency'] = $sell['currency'] ?? '';
                        $airwallexData['client_rate'] = $buy['client_rate'] ?? $buy['details']['client_rate'] ?? $sell['client_rate'] ?? $sell['details']['client_rate'] ?? null;
                        $airwallexData['currency_pair'] = $buy['currency_pair'] ?? $buy['details']['currency_pair'] ?? $sell['currency_pair'] ?? $sell['details']['currency_pair'] ?? '';
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to fetch Airwallex consolidation details: " . $e->getMessage());
            }
        }

        try {
//            \Log::info("syncLinkedBillPayment calling xeroBillService->syncPaymentToXero for transaction {$transaction->id}");
            $response = $xeroBillService->syncPaymentToXero($bill, $transaction, $airwallexData);
            $xeroPaymentId = data_get($response, 'Payments.0.PaymentID');
            if ($xeroPaymentId) {
                $transaction->update([
                    'xero_payment_id' => $xeroPaymentId,
                ]);
                return $xeroPaymentId;
            }
        } catch (\Exception $e) {
            Log::error("Failed to sync payment to Xero during linking: " . $e->getMessage());
        }

        return null;
    }

    public function linkBill(Request $request, Transaction $transaction, \App\Services\AirwallexService $airwallexService, \App\Services\XeroBillService $xeroBillService)
    {
        \Log::info("linkBill method hit for transaction ID: {$transaction->id}");

        $validated = $request->validate([
            'bill_id' => 'required|exists:bills,id',
        ]);

        $bill = \App\Models\Bill::findOrFail($validated['bill_id']);

        // Resolve exchange rate if not set but has bank transaction ID
        if (empty($transaction->exchange_rate) && !empty($transaction->bank_transaction_id)) {
            try {
                $bankData = $this->getBankTransactionData($transaction->bank_transaction_id, $airwallexService);
                if (!empty($bankData['client_rate'])) {
                    $transaction->exchange_rate = $bankData['client_rate'];
                } elseif (!empty($bankData['payment_details']['source_amount']) && !empty($bankData['payment_details']['payment_amount'])) {
                    $transaction->exchange_rate = (float) $bankData['payment_details']['payment_amount'] / (float) $bankData['payment_details']['source_amount'];
                }
            } catch (\Exception $ex) {
                \Log::warning("Could not fetch exchange rate during linkBill for transaction {$transaction->id}: " . $ex->getMessage());
            }
        }

        // --- VALIDATION: BILL REMAINING AMOUNT ---
        $remainingAmount = $this->getBillRemainingAmount($bill);
        $conversionService = app(\App\Services\CurrencyConversionService::class);
        try {
            if ($transaction->exchange_rate && $transaction->exchange_rate > 0) {
                if ($transaction->currency === 'AUD' && ($bill->currency ?? 'AUD') === 'PKR') {
                    $convertedTxAmount = (float) $transaction->amount * (float) $transaction->exchange_rate;
                } elseif ($transaction->currency === 'PKR' && ($bill->currency ?? 'AUD') === 'AUD') {
                    $convertedTxAmount = (float) $transaction->amount / (float) $transaction->exchange_rate;
                } else {
                    $convertedTxAmount = (float) $transaction->amount * (float) $transaction->exchange_rate;
                }
            } else {
                $convertedTxAmount = $conversionService->convert(
                    (float) $transaction->amount,
                    $transaction->currency,
                    $bill->currency ?? 'AUD'
                );
            }
        } catch (\Exception $e) {
            $convertedTxAmount = (float) $transaction->amount;
        }

        if (round($convertedTxAmount, 2) > round($remainingAmount, 2)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['bill_id' => ['The transaction amount exceeds the remaining unpaid amount of the selected bill.']]
            ], 422);
        }

        $transaction->update([
            'bill_id' => $bill->id,
            'is_paid' => true,
            'payment_date' => $transaction->payment_date ?: now(),
            'exchange_rate' => $transaction->exchange_rate,
        ]);

        $xeroPaymentId = $this->syncLinkedBillPayment($transaction, $airwallexService, $xeroBillService);
        $xeroSyncMessage = $xeroPaymentId ? 'Payment registered in Xero successfully.' : 'Payment registration failed or skipped.';

        $bill->recalculateStatus();

        return response()->json([
            'message' => 'Transaction linked to bill successfully.',
            'xero_sync' => $xeroSyncMessage,
            'transaction' => $transaction->load(['bill.contractor', 'bill.project', 'bill.transactions'])
        ]);
    }

    public function unlinkBill(Transaction $transaction)
    {
        $bill = $transaction->bill;

        $transaction->delete();

        if ($bill) {
            $bill->recalculateStatus();
        }

        return response()->json([
            'message' => 'Transaction unlinked and deleted successfully.',
            'transaction' => $transaction
        ]);
    }

    public function linkInvoice(Request $request, Transaction $transaction, XeroInvoiceService $xeroInvoiceService)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'stripe_fee' => 'nullable|numeric|min:0',
            'gross_amount' => 'nullable|numeric|min:0',
            'create_fee_record' => 'nullable|boolean',
        ]);

        $invoice = \App\Models\Invoice::findOrFail($validated['invoice_id']);

        $stripeFee = (float)($validated['stripe_fee'] ?? 0);
        $grossAmount = !empty($validated['gross_amount']) ? (float)$validated['gross_amount'] : null;

        // If gross amount or stripe fee provided, adjust income transaction amount so Invoice is fully credited
        if ($grossAmount !== null && $grossAmount > 0) {
            $transaction->amount = $grossAmount;
        } elseif ($stripeFee > 0 && $transaction->amount < $invoice->total_amount) {
            $transaction->amount = $transaction->amount + $stripeFee;
        }

        $transaction->update([
            'invoice_id' => $invoice->id,
            'is_paid' => true,
            'payment_date' => $transaction->payment_date ?: now(),
        ]);

        $invoice->recalculateStatus();

        // Optionally record a Stripe Processing Fee expense transaction for project accounting
        if ($stripeFee > 0 && !empty($validated['create_fee_record'])) {
            try {
                Transaction::create([
                    'project_id' => $invoice->project_id,
                    'description' => "Stripe Processing Fee (Invoice #{$invoice->id})",
                    'amount' => $stripeFee,
                    'currency' => $transaction->currency ?: ($invoice->currency ?: 'AUD'),
                    'type' => 'expense',
                    'is_paid' => true,
                    'payment_date' => $transaction->payment_date ?: now(),
                    'bank_transaction_id' => $transaction->bank_transaction_id,
                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to create Stripe fee expense transaction: " . $e->getMessage());
            }
        }

        // Sync payment to Xero if invoice has xero_invoice_id
        if (!empty($invoice->xero_invoice_id)) {
            try {
                $xeroInvoiceService->syncPaymentToXero($invoice, $transaction, [
                    'payment_amount' => $transaction->amount,
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to sync invoice payment to Xero: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Transaction linked to invoice successfully.',
            'transaction' => $transaction->load(['invoice.client', 'invoice.project', 'invoice.transactions'])
        ]);
    }

    public function unlinkInvoice(Transaction $transaction)
    {
        $invoice = $transaction->invoice;

        $transaction->delete();

        if ($invoice) {
            $invoice->recalculateStatus();
        }

        return response()->json([
            'message' => 'Transaction unlinked and deleted successfully.',
            'transaction' => $transaction
        ]);
    }

    public function destroy(Transaction $transaction)
    {
        $bill = $transaction->bill;
        $invoice = $transaction->invoice;

        $transaction->delete();

        if ($bill) {
            $bill->recalculateStatus();
        }

        if ($invoice) {
            $invoice->recalculateStatus();
        }

        return response()->json([
            'message' => 'Transaction soft deleted successfully.'
        ]);
    }

    public function restore($id)
    {
        $transaction = Transaction::withTrashed()->findOrFail($id);
        $transaction->restore();

        if ($transaction->bill) {
            $transaction->bill->recalculateStatus();
        }

        if ($transaction->invoice) {
            $transaction->invoice->recalculateStatus();
        }

        return response()->json([
            'message' => 'Transaction restored successfully.',
            'transaction' => $transaction
        ]);
    }

    public function uploadAttachment(Request $request, Transaction $transaction)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf|max:10240',
        ]);

        $file = $request->file('document');
        $objectPath = \Illuminate\Support\Facades\Storage::disk('gcs')->putFile('transactions', $file);

        $attachment = $transaction->files()->create([
            'project_id' => $transaction->project_id,
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

    /**
     * Get bank transactions from Airwallex
     */
    public function bankTransactions(Request $request, AirwallexService $airwallexService)
    {
        try {
            $params = [
                'page_num' => max(0, (int)$request->query('page', 1) - 1),
                'page_size' => $request->query('per_page', 50),
                'from_created_at' => $request->query('from_created_at', now()->subMonths(3)->format('Y-m-d')),
            ];

            if ($request->filled('status') && $request->status !== 'all') {
                $params['status'] = $request->status;
            }

            $conversionService = app(\App\Services\CurrencyConversionService::class);
            $data = $airwallexService->getTransactions($params);
            $items = $data['items'] ?? [];

            // 1. Group CONVERSION_BUY and CONVERSION_SELL transactions by source_id
            $conversionPairs = [];
            foreach ($items as $item) {
                $txType = $item['transaction_type'] ?? '';
                if (($txType === 'CONVERSION_BUY' || $txType === 'CONVERSION_SELL') && !empty($item['source_id'])) {
                    $conversionPairs[$item['source_id']][$txType] = $item;
                }
            }

            // 2. Match PAYOUTs with conversion pairs and consolidate
            $matchedIdsToRemove = [];
            foreach ($items as &$item) {
                $txType = $item['transaction_type'] ?? '';
                if ($txType === 'PAYOUT') {
                    foreach ($conversionPairs as $sourceId => $pair) {
                        $buy = $pair['CONVERSION_BUY'] ?? null;
                        $sell = $pair['CONVERSION_SELL'] ?? null;
                        if ($buy && $sell) {
                            $buyCurrency = $buy['currency'] ?? '';
                            $buyAmount = abs((float)($buy['amount'] ?? 0));
                            $payoutCurrency = $item['currency'] ?? '';
                            $payoutAmount = abs((float)($item['amount'] ?? 0));

                            $payoutTime = strtotime($item['created_at']);
                            $buyTime = strtotime($buy['created_at']);
                            $timeDiff = abs($payoutTime - $buyTime);

                            if ($buyCurrency === $payoutCurrency &&
                                abs($buyAmount - $payoutAmount) < 0.01 &&
                                $timeDiff <= 60) {

                                $item['is_consolidated'] = true;
                                $item['funding_amount'] = abs((float)($sell['amount'] ?? 0));
                                $item['funding_currency'] = $sell['currency'] ?? '';
                                $item['client_rate'] = $buy['client_rate'] ?? $buy['details']['client_rate'] ?? $sell['client_rate'] ?? $sell['details']['client_rate'] ?? null;
                                $item['currency_pair'] = $buy['currency_pair'] ?? $buy['details']['currency_pair'] ?? $sell['currency_pair'] ?? $sell['details']['currency_pair'] ?? '';

                                $matchedIdsToRemove[] = $buy['id'];
                                $matchedIdsToRemove[] = $sell['id'];
                                break;
                            }
                        }
                    }
                }
            }
            unset($item);

            // 3. Remove raw conversions that were consolidated
            if (!empty($matchedIdsToRemove)) {
                $items = array_filter($items, function($item) use ($matchedIdsToRemove) {
                    return !in_array($item['id'] ?? '', $matchedIdsToRemove);
                });
            }

            // Filter by type (default to bills)
            $type = $request->query('type', 'bills');
            if ($type === 'bills') {
                $items = array_filter($items, fn($item) => ($item['amount'] ?? 0) < 0 && ($item['transaction_type'] ?? '') === 'PAYOUT');
            } elseif ($type === 'expenses') {
                $items = array_filter($items, fn($item) => ($item['amount'] ?? 0) < 0 && ($item['transaction_type'] ?? '') === 'ISSUING_CAPTURE');
            } elseif ($type === 'other') {
                $items = array_filter($items, fn($item) => ($item['amount'] ?? 0) < 0 && ($item['transaction_type'] ?? '') !== 'PAYOUT' && ($item['transaction_type'] ?? '') !== 'ISSUING_CAPTURE');
            } elseif ($type === 'income') {
                $items = array_filter($items, fn($item) => ($item['amount'] ?? 0) > 0);
            }

            $items = array_values($items);

            // Fetch payment details ONLY for PAYOUT transactions from Cache or API
            foreach ($items as &$item) {
                if (($item['transaction_type'] ?? '') === 'PAYOUT' && !empty($item['source_id'])) {
                    $sourceId = $item['source_id'];
                    $cacheKey = 'airwallex_payment_' . $sourceId;

                    $paymentDetails = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addDays(30), function () use ($airwallexService, $sourceId) {
                        try {
                            return $airwallexService->getPayment($sourceId);
                        } catch (\Exception $e) {
                            Log::warning("Failed to fetch payment details for source_id: {$sourceId}", ['error' => $e->getMessage()]);
                            return null;
                        }
                    });

                    if ($paymentDetails) {
                        $item['payment_details'] = $paymentDetails;
                        // Inherit failed/cancelled status from payment to main transaction
                        if (in_array($paymentDetails['status'] ?? '', ['CANCELLED', 'FAILED', 'REJECTED'])) {
                            $item['status'] = 'CANCELLED';
                        }
                    }
                }
            }
            unset($item);

            if (!empty($items)) {
                $bankTxIds = collect($items)->pluck('id')->filter()->toArray();
                $localTransactions = \App\Models\Transaction::whereIn('bank_transaction_id', $bankTxIds)
                ->with(['bill.contractor', 'bill.project', 'bill.transactions', 'invoice.client', 'invoice.project', 'invoice.transactions', 'project'])
                    ->get()
                    ->groupBy('bank_transaction_id');

                foreach ($items as &$item) {
                    $txs = $localTransactions->get($item['id'], collect());
                    $bankCurrency = $item['currency'] ?? 'AUD';
                    $bankAmount = abs((float)($item['amount'] ?? 0));

                    $totalLinkedBankCurrency = 0;
                    foreach ($txs as $tx) {
                        try {
                            if ($tx->currency === $bankCurrency) {
                                $totalLinkedBankCurrency += (float)$tx->amount;
                            } elseif ($tx->exchange_rate && $tx->exchange_rate > 0) {
                                if ($tx->currency === 'AUD' && $bankCurrency === 'PKR') {
                                    $totalLinkedBankCurrency += (float)$tx->amount * (float)$tx->exchange_rate;
                                } elseif ($tx->currency === 'PKR' && $bankCurrency === 'AUD') {
                                    $totalLinkedBankCurrency += (float)$tx->amount / (float)$tx->exchange_rate;
                                } else {
                                    $totalLinkedBankCurrency += (float)$tx->amount * (float)$tx->exchange_rate;
                                }
                            } else {
                                $totalLinkedBankCurrency += $conversionService->convert(
                                    (float)$tx->amount,
                                    $tx->currency ?? 'AUD',
                                    $bankCurrency
                                );
                            }
                        } catch (\Exception $e) {
                            $totalLinkedBankCurrency += (float)$tx->amount;
                        }
                    }

                    $item['remaining_amount'] = max(0, $bankAmount - $totalLinkedBankCurrency);
                    $item['is_linked'] = $item['remaining_amount'] <= 0.01 && $txs->isNotEmpty();
                    $item['local_transactions'] = $txs;
                }
            }

            return response()->json([
                'data' => $items,
                'meta' => [
                    'has_more' => $data['has_more'] ?? false
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching bank transactions', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to fetch bank transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showBankTransaction(string $id, AirwallexService $airwallexService, StripePayoutService $stripePayoutService)
    {
        try {
            $data = $this->getBankTransactionData($id, $airwallexService);

            $possibleTexts = array_filter([
                $data['description'] ?? null,
                $data['merchant_name'] ?? null,
                $data['reference'] ?? null,
                $data['narrative'] ?? null,
                $data['payment_details']['reference'] ?? null,
                $data['details']['description'] ?? null,
            ], fn($v) => !empty($v) && is_string($v));

            $fullText = trim(implode(' ', $possibleTexts));
            $settledAt = $data['settled_at'] ?? $data['created_at'] ?? null;

            Log::info("showBankTransaction fetching Stripe details for bank ID: {$id}", [
                'fullText' => $fullText,
                'settledAt' => $settledAt,
            ]);

            $bankAmount = (float)($data['amount'] ?? 0);

            if (!empty($fullText) || $bankAmount > 0) {
                $stripeDetails = $stripePayoutService->getPayoutDetails($fullText ?: 'STRIPE', $settledAt, $bankAmount);
                if (!empty($stripeDetails)) {
                    $data['stripe_details'] = $stripeDetails;
                }
            }

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error fetching bank transaction details', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to fetch bank transaction details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper to get fully populated bank transaction data including remaining linked amount.
     */
    private function getBankTransactionData(string $id, AirwallexService $airwallexService): array
    {
        $data = $airwallexService->getTransaction($id);

        if (($data['amount'] ?? 0) < 0 && !empty($data['source_id'])) {
            $sourceId = $data['source_id'];
            $cacheKey = 'airwallex_payment_' . $sourceId;

            $paymentDetails = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addDays(30), function () use ($airwallexService, $sourceId) {
                try {
                    return $airwallexService->getPayment($sourceId);
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch payment details for source_id: {$sourceId} on single fetch", ['error' => $e->getMessage()]);
                    return null;
                }
            });

            if ($paymentDetails) {
                $data['payment_details'] = $paymentDetails;
            }
        }

            // If it is a PAYOUT, search for related conversions to consolidate
            if (($data['transaction_type'] ?? '') === 'PAYOUT') {
                try {
                    $payoutTime = strtotime($data['created_at']);
                    $nearTxs = $airwallexService->getTransactions([
                        'from_created_at' => date('Y-m-d', $payoutTime - 86400), // Check within previous day
                        'page_size' => 50
                    ]);
                    $nearItems = $nearTxs['items'] ?? [];

                    $buy = null;
                    $sell = null;
                    foreach ($nearItems as $item) {
                        $txType = $item['transaction_type'] ?? '';
                        if ($txType === 'CONVERSION_BUY') {
                            $buyCurrency = $item['currency'] ?? '';
                            $buyAmount = abs((float)($item['amount'] ?? 0));
                            $payoutCurrency = $data['currency'] ?? '';
                            $payoutAmount = abs((float)($data['amount'] ?? 0));
                            $buyTime = strtotime($item['created_at']);

                            if ($buyCurrency === $payoutCurrency &&
                                abs($buyAmount - $payoutAmount) < 0.01 &&
                                abs($payoutTime - $buyTime) <= 60) {
                                $buy = $item;
                                break;
                            }
                        }
                    }

                    if ($buy) {
                        foreach ($nearItems as $item) {
                            if (($item['transaction_type'] ?? '') === 'CONVERSION_SELL' && ($item['source_id'] ?? '') === ($buy['source_id'] ?? '')) {
                                $sell = $item;
                                break;
                            }
                        }
                    }

                    if ($buy && $sell) {
                        $data['is_consolidated'] = true;
                        $data['funding_amount'] = abs((float)($sell['amount'] ?? 0));
                        $data['funding_currency'] = $sell['currency'] ?? '';
                        $data['client_rate'] = $buy['client_rate'] ?? $buy['details']['client_rate'] ?? $sell['client_rate'] ?? $sell['details']['client_rate'] ?? null;
                        $data['currency_pair'] = $buy['currency_pair'] ?? $buy['details']['currency_pair'] ?? $sell['currency_pair'] ?? $sell['details']['currency_pair'] ?? '';
                    }
                } catch (\Exception $ex) {
                    // Fail silently, just fall back to raw transaction details
                    Log::warning('Failed to fetch conversion details for payout consolidation', ['id' => $id, 'error' => $ex->getMessage()]);
                }
            }

            $conversionService = app(\App\Services\CurrencyConversionService::class);
            $txs = \App\Models\Transaction::where('bank_transaction_id', $id)
                ->with(['bill.contractor', 'bill.project', 'bill.transactions', 'invoice.client', 'invoice.project', 'invoice.transactions', 'project'])
                ->get();

            $bankCurrency = $data['currency'] ?? 'AUD';
            $bankAmount = abs((float)($data['amount'] ?? 0));

            $totalLinkedBankCurrency = 0;
            foreach ($txs as $tx) {
                try {
                    if ($tx->currency === $bankCurrency) {
                        $totalLinkedBankCurrency += (float)$tx->amount;
                    } elseif ($tx->exchange_rate && $tx->exchange_rate > 0) {
                        if ($tx->currency === 'AUD' && $bankCurrency === 'PKR') {
                            $totalLinkedBankCurrency += (float)$tx->amount * (float)$tx->exchange_rate;
                        } elseif ($tx->currency === 'PKR' && $bankCurrency === 'AUD') {
                            $totalLinkedBankCurrency += (float)$tx->amount / (float)$tx->exchange_rate;
                        } else {
                            $totalLinkedBankCurrency += (float)$tx->amount * (float)$tx->exchange_rate;
                        }
                    } else {
                        $totalLinkedBankCurrency += $conversionService->convert(
                            (float)$tx->amount,
                            $tx->currency ?? 'AUD',
                            $bankCurrency
                        );
                    }
                } catch (\Exception $e) {
                    $totalLinkedBankCurrency += (float)$tx->amount;
                }
            }

            $data['remaining_amount'] = max(0, $bankAmount - $totalLinkedBankCurrency);
            $data['is_linked'] = $data['remaining_amount'] <= 0.01 && $txs->isNotEmpty();
            $data['local_transactions'] = $txs;

            return $data;
    }

    private function getBillRemainingAmount(\App\Models\Bill $bill): float
    {
        return $bill->remaining_amount;
    }
}
