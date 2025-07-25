<?php

namespace App\Console\Commands;

use App\Models\BonusTransaction;
use App\Models\Project;
use App\Models\User;
use App\Services\BonusProcessor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckMissedBonusesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-missed-bonuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for missed bonuses and penalties from the previous day';

    /**
     * The bonus processor instance.
     *
     * @var \App\Services\BonusProcessor
     */
    protected $bonusProcessor;

    /**
     * Create a new command instance.
     *
     * @param \App\Services\BonusProcessor $bonusProcessor
     * @return void
     */
    public function __construct(BonusProcessor $bonusProcessor)
    {
        parent::__construct();
        $this->bonusProcessor = $bonusProcessor;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for missed bonuses and penalties from the previous day...');

        // Get the date range for the previous day
        $yesterday = Carbon::yesterday();
        $startDate = $yesterday->copy()->startOfDay();
        $endDate = $yesterday->copy()->endOfDay();

        $this->info("Checking period: {$startDate->toDateTimeString()} to {$endDate->toDateTimeString()}");

        // Process missed standups
        $this->processMissedStandups($startDate, $endDate);

        // Process missed task completions
        $this->processMissedTaskCompletions($startDate, $endDate);

        // Process missed milestone completions
        $this->processMissedMilestoneCompletions($startDate, $endDate);

        $this->info('Finished checking for missed bonuses and penalties.');
    }

    /**
     * Process missed standup submissions.
     *
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @return void
     */
    protected function processMissedStandups(Carbon $startDate, Carbon $endDate)
    {
        $this->info('Processing missed standup submissions...');

        // This would need to be adapted to your actual standup model and table structure
        // For example, assuming you have a 'standups' table with user_id, project_id, and created_at columns
        try {
            $standups = DB::table('standups')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $this->info("Found {$standups->count()} standup submissions to process.");

            foreach ($standups as $standup) {
                // Check if a bonus transaction already exists for this standup
                $existingTransaction = BonusTransaction::where('source_type', 'standup')
                    ->where('source_id', $standup->id)
                    ->exists();

                if (!$existingTransaction) {
                    $this->info("Processing standup ID: {$standup->id}");

                    // Process the standup submission
                    $transaction = $this->bonusProcessor->processStandupSubmission(
                        $standup->user_id,
                        $standup->project_id,
                        $standup->id,
                        Carbon::parse($standup->created_at)
                    );

                    if ($transaction) {
                        $this->info("Created {$transaction->type} transaction: {$transaction->id}");
                    } else {
                        $this->info("No transaction created for standup ID: {$standup->id}");
                    }
                } else {
                    $this->info("Standup ID: {$standup->id} already processed, skipping.");
                }
            }
        } catch (\Exception $e) {
            $this->error("Error processing missed standups: {$e->getMessage()}");
            Log::error("Error processing missed standups: {$e->getMessage()}", [
                'exception' => $e
            ]);
        }

        // Check for missed standup penalties
        $this->checkMissedStandupPenalties($startDate, $endDate);
    }

    /**
     * Check for users who missed submitting standups and apply penalties.
     *
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @return void
     */
    protected function checkMissedStandupPenalties(Carbon $startDate, Carbon $endDate)
    {
        $this->info('Checking for missed standup penalties...');

        try {
            // Get all active projects
            $projects = Project::where('status', 'active')->get();

            foreach ($projects as $project) {
                // Get all users assigned to this project
                $users = $project->users()->get();

                foreach ($users as $user) {
                    // Check if the user submitted a standup for this project on this day
                    // This would need to be adapted to your actual standup model and table structure
                    $hasStandup = DB::table('standups')
                        ->where('user_id', $user->id)
                        ->where('project_id', $project->id)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->exists();

                    if (!$hasStandup) {
                        // Check if a penalty transaction already exists for this user/project/day
                        $existingPenalty = BonusTransaction::where('user_id', $user->id)
                            ->where('project_id', $project->id)
                            ->where('source_type', 'standup_missed')
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->exists();

                        if (!$existingPenalty) {
                            $this->info("User {$user->id} missed standup for project {$project->id}");

                            // Get applicable penalty configurations
                            $penaltyConfigs = $this->bonusProcessor->getApplicableBonusConfigurations($project, 'standup_missed');

                            if (!$penaltyConfigs->isEmpty()) {
                                $config = $penaltyConfigs->first();

                                // Create a penalty transaction
                                $transaction = BonusTransaction::create([
                                    'user_id' => $user->id,
                                    'project_id' => $project->id,
                                    'bonus_configuration_id' => $config->id,
                                    'type' => 'penalty',
                                    'amount' => $config->value,
                                    'description' => "Missed daily standup penalty for " . $startDate->format('Y-m-d'),
                                    'status' => 'pending',
                                    'source_type' => 'standup_missed',
                                    'metadata' => [
                                        'created_at' => now()->toIso8601String(),
                                        'created_by' => 'system',
                                        'missed_date' => $startDate->format('Y-m-d'),
                                    ],
                                ]);

                                $this->info("Created penalty transaction: {$transaction->id}");
                            } else {
                                $this->info("No applicable penalty configuration found for project {$project->id}");
                            }
                        } else {
                            $this->info("Penalty already exists for user {$user->id} on project {$project->id}");
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error("Error checking missed standup penalties: {$e->getMessage()}");
            Log::error("Error checking missed standup penalties: {$e->getMessage()}", [
                'exception' => $e
            ]);
        }
    }

    /**
     * Process missed task completions.
     *
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @return void
     */
    protected function processMissedTaskCompletions(Carbon $startDate, Carbon $endDate)
    {
        $this->info('Processing missed task completions...');

        // This would need to be adapted to your actual task model and table structure
        try {
            // Get tasks that were completed in the date range
            $tasks = DB::table('tasks')
                ->whereNotNull('completed_at')
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->get();

            $this->info("Found {$tasks->count()} task completions to process.");

            foreach ($tasks as $task) {
                // Check if a bonus/penalty transaction already exists for this task
                $existingTransaction = BonusTransaction::where('source_type', 'task')
                    ->where('source_id', $task->id)
                    ->orWhere(function($query) use ($task) {
                        $query->where('source_type', 'late_task')
                            ->where('source_id', $task->id);
                    })
                    ->exists();

                if (!$existingTransaction) {
                    $this->info("Processing task ID: {$task->id}");

                    // Process the task completion
                    $transaction = $this->bonusProcessor->processTaskCompletion(
                        $task->user_id,
                        $task->project_id,
                        $task->id,
                        Carbon::parse($task->completed_at),
                        Carbon::parse($task->due_date)
                    );

                    if ($transaction) {
                        $this->info("Created {$transaction->type} transaction: {$transaction->id}");
                    } else {
                        $this->info("No transaction created for task ID: {$task->id}");
                    }
                } else {
                    $this->info("Task ID: {$task->id} already processed, skipping.");
                }
            }
        } catch (\Exception $e) {
            $this->error("Error processing missed task completions: {$e->getMessage()}");
            Log::error("Error processing missed task completions: {$e->getMessage()}", [
                'exception' => $e
            ]);
        }
    }

    /**
     * Process missed milestone completions.
     *
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @return void
     */
    protected function processMissedMilestoneCompletions(Carbon $startDate, Carbon $endDate)
    {
        $this->info('Processing missed milestone completions...');

        // This would need to be adapted to your actual milestone model and table structure
        try {
            // Get milestones that were completed in the date range
            $milestones = DB::table('milestones')
                ->whereNotNull('completed_at')
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->get();

            $this->info("Found {$milestones->count()} milestone completions to process.");

            foreach ($milestones as $milestone) {
                // Check if a bonus/penalty transaction already exists for this milestone
                $existingTransaction = BonusTransaction::where('source_type', 'milestone')
                    ->where('source_id', $milestone->id)
                    ->orWhere(function($query) use ($milestone) {
                        $query->where('source_type', 'late_milestone')
                            ->where('source_id', $milestone->id);
                    })
                    ->exists();

                if (!$existingTransaction) {
                    $this->info("Processing milestone ID: {$milestone->id}");

                    // Process the milestone completion
                    $transaction = $this->bonusProcessor->processMilestoneCompletion(
                        $milestone->user_id,
                        $milestone->project_id,
                        $milestone->id,
                        Carbon::parse($milestone->completed_at),
                        Carbon::parse($milestone->due_date)
                    );

                    if ($transaction) {
                        $this->info("Created {$transaction->type} transaction: {$transaction->id}");
                    } else {
                        $this->info("No transaction created for milestone ID: {$milestone->id}");
                    }
                } else {
                    $this->info("Milestone ID: {$milestone->id} already processed, skipping.");
                }
            }
        } catch (\Exception $e) {
            $this->error("Error processing missed milestone completions: {$e->getMessage()}");
            Log::error("Error processing missed milestone completions: {$e->getMessage()}", [
                'exception' => $e
            ]);
        }
    }
}
