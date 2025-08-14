<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TransactionBonusService
{
    protected BonusCalculationService $bonusCalculationService;

    public function __construct(BonusCalculationService $bonusCalculationService)
    {
        $this->bonusCalculationService = $bonusCalculationService;
    }

    /**
     * Generates and stores transaction records for all monthly bonuses.
     *
     * @param int $year
     * @param int $month
     * @return array An array of created transactions or an error message.
     */
    public function createBonusTransactions(int $year, int $month): array
    {
        // 1. Get bonus data from the existing service.
        $bonusData = $this->bonusCalculationService->calculateMonthlyBonuses($year, $month);

        if (isset($bonusData['error'])) {
            return ['error' => 'Could not retrieve bonus data: ' . $bonusData['error']];
        }

        // 2. Find the "Team Performance" project to use for non-project-specific awards.
        $teamPerformanceProject = Project::where('name', 'Team Performance')->first();
        if (!$teamPerformanceProject) {
            Log::error("Team Performance project not found. Cannot create bonus transactions for non-project awards.");
            return ['error' => 'Team Performance project not found.'];
        }

        $transactions = [];
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        // 3. Process each award category and recipient to create transactions.
        foreach ($bonusData['awards_details'] as $awardCategory) {
            foreach ($awardCategory['recipients'] as $recipient) {
                // Determine user and project details
                $userId = $recipient['user_id'];
                $projectId = $teamPerformanceProject->id;
                $projectAward = null;
                $amountPkr = 0.0;
                $description = '';

                // Handle awards with a nested 'awards' key (Project Performance Bonus)
                if (isset($recipient['awards'])) {
                    foreach ($recipient['awards'] as $nestedAward) {
                        $projectAward = $nestedAward;
                        $projectName = $projectAward['project_details']['project_name'];
                        $project = Project::where('name', $projectName)->first();

                        $projectId = $project ? $project->id : $teamPerformanceProject->id;
                        $amountPkr = $projectAward['amount_pkr'];
                        $description = "Bonus: Project Performance for {$projectName} - {$projectAward['project_details']['bonus_reason']}";

                        // Check for duplicates before creating
                        if ($this->isDuplicateTransaction($userId, $amountPkr, $description, $startOfMonth, $endOfMonth)) {
                            Log::info("Skipping duplicate transaction for user {$userId} and project {$projectName}.");
                            continue;
                        }

                        $this->createTransaction($userId, $projectId, $amountPkr, $description, $transactions);
                    }
                } else {
                    // Handle all other awards
                    $awardTitle = $recipient['award_title'];
                    $amountPkr = $recipient['amount_pkr'];
                    $description = "Bonus: {$awardTitle}";
                    if (isset($recipient['bonus_details'])) {
                        $description .= " - {$recipient['bonus_details']}";
                    }

                    // Check for duplicates before creating
                    $existing = $this->isDuplicateTransaction($userId, $amountPkr, $description, $startOfMonth, $endOfMonth);
                    if ($existing->exists()) {
                        $existing->update([
                            'user_id' => $userId,
                            'project_id' => $projectId,
                            'amount' => $amountPkr,
                            'description' => $description,
                            'currency' => 'PKR',
                        ]);
                        Log::info("Skipping duplicate transaction for user {$userId} and award {$awardTitle}.");
                        continue;
                    }

                    $this->createTransaction($userId, $projectId, $amountPkr, $description, $transactions);
                }
            }
        }

        return $transactions;
    }

    /**
     * Checks if a transaction with the same details already exists.
     *
     * @param int $userId
     * @param float $amount
     * @param string $description
     * @param Carbon $startOfMonth
     * @param Carbon $endOfMonth
     */
    private function isDuplicateTransaction(int $userId, float $amount, string $description, Carbon $startOfMonth, Carbon $endOfMonth)
    {
        return Transaction::where('user_id', $userId)
            ->where('amount', $amount)
            ->where('type', 'bonus')
            ->where('description', 'like', $description . '%')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
    }

    /**
     * Creates a new transaction record and adds it to the transactions array.
     *
     * @param int $userId
     * @param int $projectId
     * @param float $amount
     * @param string $description
     * @param array $transactions
     */
    private function createTransaction(int $userId, int $projectId, float $amount, string $description, array &$transactions): void
    {
        $transaction = new Transaction();
        $transaction->project_id = $projectId;
        $transaction->description = $description;
        $transaction->currency = 'PKR';
        $transaction->amount = $amount;
        $transaction->is_paid = false;
        $transaction->user_id = $userId;
        $transaction->type = 'bonus';
        $transaction->save();

        $transactions[] = $transaction;
    }
}
