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

class TransactionsController extends Controller // Assuming your controller is named TransactionController
{
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
        ];

        // Validate the incoming request data against the defined rules
        // If validation fails, Laravel automatically sends a 422 Unprocessable Entity response.
        $validated = $request->validate($validationRules);

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
            'is_paid' => !empty($validated['bill_id']) || !empty($validated['invoice_id']),
            'payment_date' => (!empty($validated['bill_id']) || !empty($validated['invoice_id'])) ? now() : null,
        ]);

        if ($transaction->bill_id) {
            $transaction->bill->recalculateStatus();
        }

        if ($transaction->invoice_id) {
            $transaction->invoice->recalculateStatus();
        }

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
            'bill',
            'invoice',
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

    public function linkBill(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'bill_id' => 'required|exists:bills,id',
        ]);

        $bill = \App\Models\Bill::findOrFail($validated['bill_id']);

        $transaction->update([
            'bill_id' => $bill->id,
            'is_paid' => true,
            'payment_date' => $transaction->payment_date ?: now(),
        ]);

        $bill->recalculateStatus();

        return response()->json([
            'message' => 'Transaction linked to bill successfully.',
            'transaction' => $transaction->load('bill')
        ]);
    }

    public function unlinkBill(Transaction $transaction)
    {
        $bill = $transaction->bill;

        $transaction->update([
            'bill_id' => null,
        ]);

        if ($bill) {
            $bill->recalculateStatus();
        }

        return response()->json([
            'message' => 'Transaction unlinked from bill successfully.',
            'transaction' => $transaction
        ]);
    }

    public function linkInvoice(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
        ]);

        $invoice = \App\Models\Invoice::findOrFail($validated['invoice_id']);

        $transaction->update([
            'invoice_id' => $invoice->id,
            'is_paid' => true,
            'payment_date' => $transaction->payment_date ?: now(),
        ]);

        $invoice->recalculateStatus();

        return response()->json([
            'message' => 'Transaction linked to invoice successfully.',
            'transaction' => $transaction->load('invoice')
        ]);
    }

    public function unlinkInvoice(Transaction $transaction)
    {
        $invoice = $transaction->invoice;

        $transaction->update([
            'invoice_id' => null,
        ]);

        if ($invoice) {
            $invoice->recalculateStatus();
        }

        return response()->json([
            'message' => 'Transaction unlinked from invoice successfully.',
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
                'page_size' => $request->query('per_page', 50),
                'from_created_at' => $request->query('from_created_at', now()->subMonths(3)->format('Y-m-d')),
            ];
            
            // Note: Add any date or search filters supported by Airwallex API here.

            $data = $airwallexService->getTransactions($params);
            $items = $data['items'] ?? [];
            
            if (!empty($items)) {
                $bankTxIds = collect($items)->pluck('id')->filter()->toArray();
                $localTransactions = \App\Models\Transaction::whereIn('bank_transaction_id', $bankTxIds)
                    ->with(['bill', 'invoice', 'project'])
                    ->get()
                    ->keyBy('bank_transaction_id');
                
                foreach ($items as &$item) {
                    $localTx = $localTransactions->get($item['id']);
                    $item['is_linked'] = !empty($localTx);
                    $item['local_transaction'] = $localTx;
                }
            }
            
            // Typically the API returns { "items": [...], "has_more": true }
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
}
