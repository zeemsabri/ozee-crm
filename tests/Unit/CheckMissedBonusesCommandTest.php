<?php

namespace Tests\Unit;

use App\Console\Commands\CheckMissedBonusesCommand;
use App\Models\BonusConfiguration;
use App\Models\BonusConfigurationGroup;
use App\Models\BonusTransaction;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckMissedBonusesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $project;

    protected $bonusConfig;

    protected $penaltyConfig;

    protected $command;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();

        // Create a test project
        $this->project = Project::factory()->create([
            'status' => 'active',
        ]);

        // Attach the user to the project
        $this->project->users()->attach($this->user->id, ['role_id' => 1]); // Assuming role_id 1 is a valid role

        // Create a bonus configuration group
        $group = BonusConfigurationGroup::create([
            'name' => 'Test Group',
            'description' => 'Test group for unit tests',
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        // Create a bonus configuration for standups
        $this->bonusConfig = BonusConfiguration::create([
            'name' => 'Daily Standup Bonus',
            'type' => 'bonus',
            'amountType' => 'fixed',
            'value' => 10.00,
            'appliesTo' => 'standup',
            'isActive' => true,
            'uuid' => 'test-bonus-uuid',
            'user_id' => $this->user->id,
        ]);

        // Create a penalty configuration for missed standups
        $this->penaltyConfig = BonusConfiguration::create([
            'name' => 'Missed Standup Penalty',
            'type' => 'penalty',
            'amountType' => 'fixed',
            'value' => 5.00,
            'appliesTo' => 'standup_missed',
            'isActive' => true,
            'uuid' => 'test-penalty-uuid',
            'user_id' => $this->user->id,
        ]);

        // Create a task bonus configuration
        $this->taskBonusConfig = BonusConfiguration::create([
            'name' => 'On-Time Task Bonus',
            'type' => 'bonus',
            'amountType' => 'fixed',
            'value' => 15.00,
            'appliesTo' => 'task',
            'isActive' => true,
            'uuid' => 'test-task-bonus-uuid',
            'user_id' => $this->user->id,
        ]);

        // Create a late task penalty configuration
        $this->taskPenaltyConfig = BonusConfiguration::create([
            'name' => 'Late Task Penalty',
            'type' => 'penalty',
            'amountType' => 'fixed',
            'value' => 8.00,
            'appliesTo' => 'late_task',
            'isActive' => true,
            'uuid' => 'test-late-task-penalty-uuid',
            'user_id' => $this->user->id,
        ]);

        // Attach configurations to the group
        $group->bonusConfigurations()->attach($this->bonusConfig->id, ['sort_order' => 0]);
        $group->bonusConfigurations()->attach($this->penaltyConfig->id, ['sort_order' => 1]);
        $group->bonusConfigurations()->attach($this->taskBonusConfig->id, ['sort_order' => 2]);
        $group->bonusConfigurations()->attach($this->taskPenaltyConfig->id, ['sort_order' => 3]);

        // Attach the group to the project
        $this->project->bonusConfigurationGroups()->attach($group->id);

        // Create the command instance
        $this->command = new CheckMissedBonusesCommand;
    }

    /**
     * Test that the command processes missed standup submissions.
     */
    public function test_command_processes_missed_standup_submissions(): void
    {
        // Arrange
        // Create a standup table if it doesn't exist (for testing purposes)
        if (! DB::getSchemaBuilder()->hasTable('standups')) {
            DB::statement('CREATE TABLE standups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                project_id INTEGER,
                content TEXT,
                created_at DATETIME,
                updated_at DATETIME
            )');
        }

        // Insert a test standup from yesterday
        $yesterday = Carbon::yesterday();
        $standupId = DB::table('standups')->insertGetId([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'content' => 'Test standup content',
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);

        // Act
        // Run the command
        Artisan::call('app:check-missed-bonuses');

        // Assert
        // Check that a bonus transaction was created for the standup
        $transaction = BonusTransaction::where('source_type', 'standup')
            ->where('source_id', $standupId)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('bonus', $transaction->type);
        $this->assertEquals(10.00, $transaction->amount);
        $this->assertEquals($this->user->id, $transaction->user_id);
        $this->assertEquals($this->project->id, $transaction->project_id);
        $this->assertEquals($this->bonusConfig->id, $transaction->bonus_configuration_id);
    }

    /**
     * Test that the command applies penalties for missed standups.
     */
    public function test_command_applies_penalties_for_missed_standups(): void
    {
        // Arrange
        // Create a standup table if it doesn't exist (for testing purposes)
        if (! DB::getSchemaBuilder()->hasTable('standups')) {
            DB::statement('CREATE TABLE standups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                project_id INTEGER,
                content TEXT,
                created_at DATETIME,
                updated_at DATETIME
            )');
        }

        // No standups for yesterday (simulating a missed standup)

        // Act
        // Run the command
        Artisan::call('app:check-missed-bonuses');

        // Assert
        // Check that a penalty transaction was created for the missed standup
        $transaction = BonusTransaction::where('source_type', 'standup_missed')
            ->where('user_id', $this->user->id)
            ->where('project_id', $this->project->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('penalty', $transaction->type);
        $this->assertEquals(5.00, $transaction->amount);
        $this->assertEquals($this->penaltyConfig->id, $transaction->bonus_configuration_id);
    }

    /**
     * Test that the command processes completed tasks.
     */
    public function test_command_processes_completed_tasks(): void
    {
        // Arrange
        // Create a tasks table if it doesn't exist (for testing purposes)
        if (! DB::getSchemaBuilder()->hasTable('tasks')) {
            DB::statement('CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                project_id INTEGER,
                title TEXT,
                description TEXT,
                due_date DATETIME,
                completed_at DATETIME,
                created_at DATETIME,
                updated_at DATETIME
            )');
        }

        // Insert a test task completed yesterday (on time)
        $yesterday = Carbon::yesterday();
        $dueDate = $yesterday->copy()->addDays(1); // Due tomorrow, so it's on time
        $taskId = DB::table('tasks')->insertGetId([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'title' => 'Test task',
            'description' => 'Test task description',
            'due_date' => $dueDate,
            'completed_at' => $yesterday,
            'created_at' => $yesterday->copy()->subDays(1),
            'updated_at' => $yesterday,
        ]);

        // Insert a test task completed yesterday (late)
        $lateTaskId = DB::table('tasks')->insertGetId([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'title' => 'Late test task',
            'description' => 'Late test task description',
            'due_date' => $yesterday->copy()->subDays(1), // Due day before yesterday, so it's late
            'completed_at' => $yesterday,
            'created_at' => $yesterday->copy()->subDays(2),
            'updated_at' => $yesterday,
        ]);

        // Act
        // Run the command
        Artisan::call('app:check-missed-bonuses');

        // Assert
        // Check that a bonus transaction was created for the on-time task
        $bonusTransaction = BonusTransaction::where('source_type', 'task')
            ->where('source_id', $taskId)
            ->first();

        $this->assertNotNull($bonusTransaction);
        $this->assertEquals('bonus', $bonusTransaction->type);
        $this->assertEquals(15.00, $bonusTransaction->amount);
        $this->assertEquals($this->user->id, $bonusTransaction->user_id);
        $this->assertEquals($this->project->id, $bonusTransaction->project_id);
        $this->assertEquals($this->taskBonusConfig->id, $bonusTransaction->bonus_configuration_id);

        // Check that a penalty transaction was created for the late task
        $penaltyTransaction = BonusTransaction::where('source_type', 'late_task')
            ->where('source_id', $lateTaskId)
            ->first();

        $this->assertNotNull($penaltyTransaction);
        $this->assertEquals('penalty', $penaltyTransaction->type);
        $this->assertEquals(8.00, $penaltyTransaction->amount);
        $this->assertEquals($this->user->id, $penaltyTransaction->user_id);
        $this->assertEquals($this->project->id, $penaltyTransaction->project_id);
        $this->assertEquals($this->taskPenaltyConfig->id, $penaltyTransaction->bonus_configuration_id);
    }

    /**
     * Test that the command doesn't create duplicate transactions.
     */
    public function test_command_does_not_create_duplicate_transactions(): void
    {
        // Arrange
        // Create a standup table if it doesn't exist (for testing purposes)
        if (! DB::getSchemaBuilder()->hasTable('standups')) {
            DB::statement('CREATE TABLE standups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                project_id INTEGER,
                content TEXT,
                created_at DATETIME,
                updated_at DATETIME
            )');
        }

        // Insert a test standup from yesterday
        $yesterday = Carbon::yesterday();
        $standupId = DB::table('standups')->insertGetId([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'content' => 'Test standup content',
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);

        // Create a transaction for this standup (simulating it's already been processed)
        BonusTransaction::create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'bonus_configuration_id' => $this->bonusConfig->id,
            'type' => 'bonus',
            'amount' => 10.00,
            'description' => 'Daily standup bonus for '.$yesterday->format('Y-m-d'),
            'status' => 'pending',
            'source_type' => 'standup',
            'source_id' => $standupId,
            'metadata' => [
                'created_at' => now()->toIso8601String(),
                'created_by' => 'system',
            ],
        ]);

        // Act
        // Run the command
        Artisan::call('app:check-missed-bonuses');

        // Assert
        // Check that no new transaction was created for the standup
        $transactionCount = BonusTransaction::where('source_type', 'standup')
            ->where('source_id', $standupId)
            ->count();

        $this->assertEquals(1, $transactionCount);
    }

    /**
     * Test that the command handles errors gracefully.
     */
    public function test_command_handles_errors_gracefully(): void
    {
        // Arrange
        // Create a standup table if it doesn't exist (for testing purposes)
        if (! DB::getSchemaBuilder()->hasTable('standups')) {
            DB::statement('CREATE TABLE standups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                project_id INTEGER,
                content TEXT,
                created_at DATETIME,
                updated_at DATETIME
            )');
        }

        // Insert a test standup with an invalid user ID
        $yesterday = Carbon::yesterday();
        $standupId = DB::table('standups')->insertGetId([
            'user_id' => 9999, // Invalid user ID
            'project_id' => $this->project->id,
            'content' => 'Test standup content',
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);

        // Act
        // Run the command
        Artisan::call('app:check-missed-bonuses');

        // Assert
        // The command should not crash, and no transaction should be created
        $transaction = BonusTransaction::where('source_type', 'standup')
            ->where('source_id', $standupId)
            ->first();

        $this->assertNull($transaction);
    }
}
