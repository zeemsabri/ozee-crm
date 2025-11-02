<?php

namespace Tests\Unit;

use App\Models\BonusConfiguration;
use App\Models\BonusConfigurationGroup;
use App\Models\BonusTransaction;
use App\Models\Project;
use App\Models\User;
use App\Services\BonusProcessor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BonusProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected $bonusProcessor;

    protected $user;

    protected $project;

    protected $bonusConfig;

    protected $penaltyConfig;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create the BonusProcessor instance
        $this->bonusProcessor = new BonusProcessor;

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

        // Attach configurations to the group
        $group->bonusConfigurations()->attach($this->bonusConfig->id, ['sort_order' => 0]);
        $group->bonusConfigurations()->attach($this->penaltyConfig->id, ['sort_order' => 1]);

        // Attach the group to the project
        $this->project->bonusConfigurationGroups()->attach($group->id);
    }

    /**
     * Test processing a standup submission for bonus.
     */
    public function test_process_standup_submission_creates_bonus_transaction(): void
    {
        // Arrange
        $standupId = 'test-standup-123';
        $submissionDate = Carbon::now();

        // Act
        $transaction = $this->bonusProcessor->processStandupSubmission(
            $this->user->id,
            $this->project->id,
            $standupId,
            $submissionDate
        );

        // Assert
        $this->assertNotNull($transaction);
        $this->assertInstanceOf(BonusTransaction::class, $transaction);
        $this->assertEquals('bonus', $transaction->type);
        $this->assertEquals(10.00, $transaction->amount);
        $this->assertEquals($this->user->id, $transaction->user_id);
        $this->assertEquals($this->project->id, $transaction->project_id);
        $this->assertEquals($this->bonusConfig->id, $transaction->bonus_configuration_id);
        $this->assertEquals('standup', $transaction->source_type);
        $this->assertEquals($standupId, $transaction->source_id);
    }

    /**
     * Test that a bonus is not applied on weekends.
     */
    public function test_standup_bonus_not_applied_on_weekends(): void
    {
        // Arrange
        $standupId = 'test-standup-weekend';
        $submissionDate = Carbon::now()->next(Carbon::SATURDAY); // Next Saturday

        // Act
        $transaction = $this->bonusProcessor->processStandupSubmission(
            $this->user->id,
            $this->project->id,
            $standupId,
            $submissionDate
        );

        // Assert
        $this->assertNull($transaction);
        $this->assertEquals(0, BonusTransaction::count());
    }

    /**
     * Test processing an on-time task completion for bonus.
     */
    public function test_process_on_time_task_completion_creates_bonus_transaction(): void
    {
        // Arrange
        // Create a task bonus configuration
        $taskBonusConfig = BonusConfiguration::create([
            'name' => 'On-Time Task Bonus',
            'type' => 'bonus',
            'amountType' => 'fixed',
            'value' => 15.00,
            'appliesTo' => 'task',
            'isActive' => true,
            'uuid' => 'test-task-bonus-uuid',
            'user_id' => $this->user->id,
        ]);

        // Attach to the group and project
        $group = $this->project->bonusConfigurationGroups()->first();
        $group->bonusConfigurations()->attach($taskBonusConfig->id, ['sort_order' => 2]);

        $taskId = 'test-task-123';
        $completionDate = Carbon::now();
        $dueDate = Carbon::now()->addDays(1); // Due tomorrow, so it's on time

        // Act
        $transaction = $this->bonusProcessor->processTaskCompletion(
            $this->user->id,
            $this->project->id,
            $taskId,
            $completionDate,
            $dueDate
        );

        // Assert
        $this->assertNotNull($transaction);
        $this->assertInstanceOf(BonusTransaction::class, $transaction);
        $this->assertEquals('bonus', $transaction->type);
        $this->assertEquals(15.00, $transaction->amount);
        $this->assertEquals($this->user->id, $transaction->user_id);
        $this->assertEquals($this->project->id, $transaction->project_id);
        $this->assertEquals($taskBonusConfig->id, $transaction->bonus_configuration_id);
        $this->assertEquals('task', $transaction->source_type);
        $this->assertEquals($taskId, $transaction->source_id);
    }

    /**
     * Test processing a late task completion for penalty.
     */
    public function test_process_late_task_completion_creates_penalty_transaction(): void
    {
        // Arrange
        // Create a late task penalty configuration
        $lateTaskPenaltyConfig = BonusConfiguration::create([
            'name' => 'Late Task Penalty',
            'type' => 'penalty',
            'amountType' => 'fixed',
            'value' => 8.00,
            'appliesTo' => 'late_task',
            'isActive' => true,
            'uuid' => 'test-late-task-penalty-uuid',
            'user_id' => $this->user->id,
        ]);

        // Attach to the group and project
        $group = $this->project->bonusConfigurationGroups()->first();
        $group->bonusConfigurations()->attach($lateTaskPenaltyConfig->id, ['sort_order' => 3]);

        $taskId = 'test-task-456';
        $completionDate = Carbon::now();
        $dueDate = Carbon::now()->subDays(1); // Due yesterday, so it's late

        // Act
        $transaction = $this->bonusProcessor->processTaskCompletion(
            $this->user->id,
            $this->project->id,
            $taskId,
            $completionDate,
            $dueDate
        );

        // Assert
        $this->assertNotNull($transaction);
        $this->assertInstanceOf(BonusTransaction::class, $transaction);
        $this->assertEquals('penalty', $transaction->type);
        $this->assertEquals(8.00, $transaction->amount);
        $this->assertEquals($this->user->id, $transaction->user_id);
        $this->assertEquals($this->project->id, $transaction->project_id);
        $this->assertEquals($lateTaskPenaltyConfig->id, $transaction->bonus_configuration_id);
        $this->assertEquals('late_task', $transaction->source_type);
        $this->assertEquals($taskId, $transaction->source_id);
    }

    /**
     * Test processing an on-time milestone completion for bonus.
     */
    public function test_process_on_time_milestone_completion_creates_bonus_transaction(): void
    {
        // Arrange
        // Create a milestone bonus configuration
        $milestoneBonusConfig = BonusConfiguration::create([
            'name' => 'On-Time Milestone Bonus',
            'type' => 'bonus',
            'amountType' => 'fixed',
            'value' => 25.00,
            'appliesTo' => 'milestone',
            'isActive' => true,
            'uuid' => 'test-milestone-bonus-uuid',
            'user_id' => $this->user->id,
        ]);

        // Attach to the group and project
        $group = $this->project->bonusConfigurationGroups()->first();
        $group->bonusConfigurations()->attach($milestoneBonusConfig->id, ['sort_order' => 4]);

        $milestoneId = 'test-milestone-123';
        $completionDate = Carbon::now();
        $dueDate = Carbon::now()->addDays(1); // Due tomorrow, so it's on time

        // Act
        $transaction = $this->bonusProcessor->processMilestoneCompletion(
            $this->user->id,
            $this->project->id,
            $milestoneId,
            $completionDate,
            $dueDate
        );

        // Assert
        $this->assertNotNull($transaction);
        $this->assertInstanceOf(BonusTransaction::class, $transaction);
        $this->assertEquals('bonus', $transaction->type);
        $this->assertEquals(25.00, $transaction->amount);
        $this->assertEquals($this->user->id, $transaction->user_id);
        $this->assertEquals($this->project->id, $transaction->project_id);
        $this->assertEquals($milestoneBonusConfig->id, $transaction->bonus_configuration_id);
        $this->assertEquals('milestone', $transaction->source_type);
        $this->assertEquals($milestoneId, $transaction->source_id);
    }

    /**
     * Test processing a late milestone completion for penalty.
     */
    public function test_process_late_milestone_completion_creates_penalty_transaction(): void
    {
        // Arrange
        // Create a late milestone penalty configuration
        $lateMilestonePenaltyConfig = BonusConfiguration::create([
            'name' => 'Late Milestone Penalty',
            'type' => 'penalty',
            'amountType' => 'fixed',
            'value' => 12.00,
            'appliesTo' => 'late_milestone',
            'isActive' => true,
            'uuid' => 'test-late-milestone-penalty-uuid',
            'user_id' => $this->user->id,
        ]);

        // Attach to the group and project
        $group = $this->project->bonusConfigurationGroups()->first();
        $group->bonusConfigurations()->attach($lateMilestonePenaltyConfig->id, ['sort_order' => 5]);

        $milestoneId = 'test-milestone-456';
        $completionDate = Carbon::now();
        $dueDate = Carbon::now()->subDays(1); // Due yesterday, so it's late

        // Act
        $transaction = $this->bonusProcessor->processMilestoneCompletion(
            $this->user->id,
            $this->project->id,
            $milestoneId,
            $completionDate,
            $dueDate
        );

        // Assert
        $this->assertNotNull($transaction);
        $this->assertInstanceOf(BonusTransaction::class, $transaction);
        $this->assertEquals('penalty', $transaction->type);
        $this->assertEquals(12.00, $transaction->amount);
        $this->assertEquals($this->user->id, $transaction->user_id);
        $this->assertEquals($this->project->id, $transaction->project_id);
        $this->assertEquals($lateMilestonePenaltyConfig->id, $transaction->bonus_configuration_id);
        $this->assertEquals('late_milestone', $transaction->source_type);
        $this->assertEquals($milestoneId, $transaction->source_id);
    }

    /**
     * Test that duplicate transactions are not created.
     */
    public function test_duplicate_transactions_are_not_created(): void
    {
        // Arrange
        $standupId = 'test-standup-duplicate';
        $submissionDate = Carbon::now();

        // Act - Create the first transaction
        $transaction1 = $this->bonusProcessor->processStandupSubmission(
            $this->user->id,
            $this->project->id,
            $standupId,
            $submissionDate
        );

        // Try to create a duplicate transaction
        $transaction2 = $this->bonusProcessor->processStandupSubmission(
            $this->user->id,
            $this->project->id,
            $standupId,
            $submissionDate
        );

        // Assert
        $this->assertNotNull($transaction1);
        $this->assertNull($transaction2); // Second transaction should not be created
        $this->assertEquals(1, BonusTransaction::where('source_id', $standupId)->count());
    }

    /**
     * Test error handling when processing invalid data.
     */
    public function test_error_handling_with_invalid_data(): void
    {
        // Arrange
        $invalidUserId = 9999; // Non-existent user ID
        $standupId = 'test-standup-error';
        $submissionDate = Carbon::now();

        // Act
        $transaction = $this->bonusProcessor->processStandupSubmission(
            $invalidUserId,
            $this->project->id,
            $standupId,
            $submissionDate
        );

        // Assert
        $this->assertNull($transaction); // Should return null on error
        $this->assertEquals(0, BonusTransaction::where('source_id', $standupId)->count());
    }
}
