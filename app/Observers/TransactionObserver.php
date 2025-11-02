<?php

namespace App\Observers;

use App\Http\Controllers\Api\Concerns\HasFinancialCalculations;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    use HasFinancialCalculations;

    /**
     * Handle the Transaction "created" event.
     *
     * @return void
     */
    public function created(Transaction $transaction)
    {
        $this->updateProjectProfitMargin($transaction);
    }

    /**
     * Handle the Transaction "updated" event.
     *
     * @return void
     */
    public function updated(Transaction $transaction)
    {
        $this->updateProjectProfitMargin($transaction);
    }

    /**
     * Handle the Transaction "deleted" event.
     *
     * @return void
     */
    public function deleted(Transaction $transaction)
    {
        $this->updateProjectProfitMargin($transaction);
    }

    /**
     * Update the project's profit margin percentage based on transactions.
     *
     * @return void
     */
    private function updateProjectProfitMargin(Transaction $transaction)
    {
        // Get the project associated with this transaction
        $project = $transaction->project;

        if (! $project) {
            Log::warning('Transaction has no associated project', [
                'transaction_id' => $transaction->id,
            ]);

            return;
        }

        // Get all transactions for this project
        $transactions = $project->transactions;

        if ($transactions->isEmpty()) {
            // No transactions, set profit margin to null
            $project->profit_margin_percentage = null;
            $project->save();

            return;
        }

        // Process transactions to get financial stats (using HasFinancialCalculations trait)
        $result = $this->processTransactionsForDisplay($transactions, 'USD');
        $stats = $result['stats'];

        $totalIncome = $stats['totalIncome'];
        $totalExpense = $stats['totalExpense'];

        // Calculate profit margin percentage
        if ($totalIncome > 0) {
            $profitMargin = (($totalIncome - $totalExpense) / $totalIncome) * 100;
            $project->profit_margin_percentage = round($profitMargin, 2);
        } else {
            // If no income, profit margin is undefined
            $project->profit_margin_percentage = null;
        }

        $project->save();

        Log::info('Updated project profit margin', [
            'project_id' => $project->id,
            'profit_margin_percentage' => $project->profit_margin_percentage,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
        ]);
    }
}
