<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        Gate::authorize('addTransactions', $project);

        // Define validation rules for a single transaction object
        // The request body is expected to contain description, amount, type, etc., directly.
        $validationRules = [
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'user_id' => 'nullable|exists:users,id', // User ID is optional
            'currency' =>   'required|string',
            'hours_spent' => 'nullable|numeric|min:0', // Hours spent is optional
            'type' => 'required|in:income,expense', // Type must be 'income' or 'expense'
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
            'payment_amount' => 'required|numeric|min:0.01',
            'pay_in_full' => 'required|boolean',
        ]);

        $paymentAmount = (float) $validated['payment_amount'];
        $payInFull = (bool) $validated['pay_in_full'];
        $remainingAmount = (float) $transaction->amount;

        // Backend validation: Ensure payment amount is not more than remaining amount
        if (!$payInFull && $paymentAmount > $remainingAmount) {
            return response()->json([
                'errors' => ['payment_amount' => ['Payment amount cannot exceed the remaining balance.']],
                'message' => 'The given data was invalid.'
            ], 422);
        }

        // Use a database transaction to ensure atomicity
        DB::beginTransaction();

        try {
            if ($payInFull) {
                // 3. Handle Full Payment
                $transaction->update([
                    'is_paid' => true
                ]);
            } else {
                // 4. Handle Partial Payment

                // Calculate the new remaining amount for the original transaction
                $updatedRemainingAmount = $remainingAmount - $paymentAmount;

                // Update the original transaction's remaining amount
                $transaction->update([
                    'amount' => $updatedRemainingAmount
                ]);

                // Create a new transaction for the paid portion
                $newTransaction = $project->transactions()->create([
                    'description' => $transaction->description . ' (Partial Payment)',
                    'amount' => $paymentAmount,
                    'currency' => $transaction->currency, // Use parent transaction's currency
                    'user_id' => $transaction->user_id, // Assign to the same user if applicable
                    'type' => $transaction->type,
                    'is_paid' => true, // This new transaction is specifically for the paid amount
                    'transaction_id' => $transaction->id, // Link to the parent transaction
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Payment processed successfully.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment processing failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to process payment.', 'error' => $e->getMessage()], 500);
        }
    }


}
