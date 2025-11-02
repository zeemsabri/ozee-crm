<?php

namespace App\Actions\Points;

use App\Models\PointsLedger;
use App\Models\ProjectNote;
use App\Models\Task;
use App\Services\LedgerService;
use App\Services\TaskService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AwardTaskPointsAction
{
    // Constants for points and penalties
    const BASE_POINTS_TASK_ON_TIME = 50;

    const BASE_POINTS_TASK_EARLY = 100;

    const LATE_STANDUP_REDUCTION_PERCENTAGE = 0.25;

    /**
     * @var LedgerService
     */
    protected $ledgerService;

    /**
     * @var TaskService
     */
    protected $taskService;

    /**
     * AwardTaskPointsAction constructor.
     */
    public function __construct(LedgerService $ledgerService, TaskService $taskService)
    {
        $this->ledgerService = $ledgerService;
        $this->taskService = $taskService;
    }

    /**
     * Executes the business logic for awarding points for a completed task.
     *
     * @param  Task  $task  The Task model object.
     * @return PointsLedger|null The newly created PointsLedger model instance, or null if points were not awarded.
     */
    public function execute(Task $task): ?PointsLedger
    {
        // Note: For optimal performance, the calling code should eager-load the 'assignee', 'milestone', and 'milestone.project' relationships.
        // E.g., Task::with('assignee', 'milestone.project')->find(...)

        // 1. Defensive Checks
        if (is_null($task->assignee)) {
            Log::warning("AwardTaskPointsAction was called for task with ID {$task->id} but no assignee was found. Points not awarded.");

            return null;
        }

        // Check if the task is properly linked to a project via a milestone.
        if (is_null($task->milestone) || is_null($task->milestone->project_id)) {
            return $this->ledgerService->record(
                $task->assignee,
                0,
                'Denied: Task is not linked to a project via a milestone.'.' Task: '.$task->name,
                'denied',
                $task,
                $task->milestone?->project
            );
        }

        // 2. Deduplication Check: Check if points have already been awarded for this specific Task.
        $existingEntry = PointsLedger::where('pointable_id', $task->id)
            ->where('user_id', $task->assignee->id)
            ->where('pointable_type', Task::class)
            ->first();

        if ($existingEntry) {
            return $this->ledgerService->record(
                $task->assignee,
                0,
                'Denied: Points already awarded for this task.'.': '.$task->name.' Existing Point ID: '.$existingEntry->id,
                'denied',
                $task,
                $task->milestone?->project
            );
        }

        // 3. Prerequisite Standup Check: A standup must have been submitted on the day the task was completed.
        $userTimezone = $task->assignee->timezone;
        $startOfDay = Carbon::parse($task->actual_completion_date, $userTimezone)->startOfDay()->setTimezone('UTC');
        $endOfDay = Carbon::parse($task->actual_completion_date, $userTimezone)->endOfDay()->setTimezone('UTC');
        $date = $task->actual_completion_date;

        $standupForDay = ProjectNote::where('user_id', $task->assignee->id)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->first();

        if (! $standupForDay) {
            return $this->ledgerService->record(
                $task->assignee,
                0,
                'Denied: No standup found on the day of task completion.'.': '.$date,
                'denied',
                $task,
                $task->milestone?->project,
                $date
            );
        }

        // 4. Calculate Points: Use the TaskService to determine the points.
        $pointsToAward = 0;
        $description = '';

        if ($this->taskService->isTaskEarly($task)) {
            $pointsToAward = self::BASE_POINTS_TASK_EARLY;
            $description = 'Early Task Completion: '.$task->title;
        } elseif ($this->taskService->isTaskOnTime($task)) {
            $pointsToAward = self::BASE_POINTS_TASK_ON_TIME;
            $description = 'On-Time Task Completion: '.$task->title;
        } else {
            // If the task was not on time, record a denied transaction and stop.
            return $this->ledgerService->record(
                $task->assignee,
                0,
                'Denied: Task was not completed on time.',
                'denied',
                $task,
                $task->milestone?->project,
                $date
            );
        }

        // 5. Point Reduction for Late Standup:
        if (! $standupForDay->isBeforeUserTime('11:00:00')) {
            $deduction = $pointsToAward * self::LATE_STANDUP_REDUCTION_PERCENTAGE;
            $pointsToAward -= $deduction;
            $description .= ' (Reduced due to late standup)';
        }

        // 6. Record the Final Transaction:
        return $this->ledgerService->record(
            $task->assignee,
            $pointsToAward,
            $description,
            'paid',
            $task,
            $task->milestone?->project,
            $date
        );
    }
}
