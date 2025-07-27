<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TransactionsController extends Controller // Assuming your controller is named TransactionController
{
    /**
     * Add a single transaction (income or expense) to a project.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Project  $project
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
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'user_id' => 'nullable|exists:users,id', // User ID is optional
            'currency' =>   'required|string',
            'hours_spent' => 'nullable|numeric|min:0', // Hours spent is optional
            'type' => 'required|in:income,expense,bonus', // Type must be 'income' or 'expense'
        ];

        // Validate the incoming request data against the defined rules
        // If validation fails, Laravel automatically sends a 422 Unprocessable Entity response.
        $validated = $request->validate($validationRules);

        // Create a single transaction record in the database
        // The create method on the relationship automatically sets the project_id.
        $transaction = $project->transactions()->create([
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'currency'  =>  $validated['currency'],
            'user_id' => $validated['user_id'] ?? null, // Use null if user_id is not provided
            'hours_spent' => $validated['hours_spent'] ?? null, // Use null if hours_spent is not provided
            'type' => $validated['type'],
        ]);

        // Return the newly created transaction as a JSON response with a 201 Created status
        return response()->json($transaction, 201);
    }

    /**
     * Handle payment processing for a specific transaction.
     * This function manages both full and partial payments.
     *
     * @param Request $request
     * @param Project $project The project the transaction belongs to.
     * @param Transaction $transaction The specific transaction being paid.
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
        if (!$isActualFullPayment && $paymentAmount > $originalTransactionAmount) {
            return response()->json([
                'errors' => ['payment_amount' => ['Payment amount cannot exceed the remaining balance of the original transaction.']],
                'message' => 'The given data was invalid.'
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
                    'description' => $transaction->description . ' (Partial ' . ucfirst($transaction->type) . ')', // More descriptive
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
            Log::error('Payment processing failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to process payment.', 'error' => $e->getMessage()], 500);
        }
    }


}
