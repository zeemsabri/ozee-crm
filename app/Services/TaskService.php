<?php

namespace App\Services;

use App\Models\Task;
use Carbon\Carbon;

/**
 * Class TaskService
 *
 * This service handles all complex business logic related to tasks,
 * acting as a definitive source for business rules.
 */
class TaskService
{
    /**
     * Determines if a task is overdue based on its assigned user's timezone.
     *
     * @param Task $task The task model object.
     * @param string $deadlineTime Optional time string, e.g., '17:00:00'. Defaults to a full day check.
     * @return bool True if the task is overdue, false otherwise.
     */
    public function isTaskOverdue(Task $task, string $deadlineTime = '23:59:59'): bool
    {
        // Check for necessary data before proceeding.
        if (!$task->assignee || !$task->assignee->timezone || !$task->due_date) {
            // Log this as a warning or return based on your application's needs.
            return false;
        }

        // Retrieve the assignee's timezone.
        $assigneeTimezone = $task->assignee->timezone;

        // Combine the due date and the deadline time in the assignee's timezone.
        $deadlineMoment = Carbon::parse(
            $task->due_date . ' ' . $deadlineTime,
            $assigneeTimezone
        );

        // Compare the deadline moment to the current time in the assignee's timezone.
        return Carbon::now($assigneeTimezone)->greaterThan($deadlineMoment);
    }

    /**
     * Determines if a task was completed on or before its due date.
     *
     * The check is timezone-aware, based on the task assignee's timezone.
     *
     * @param Task $task
     * @return bool
     */
    public function isTaskOnTime(Task $task): bool
    {
        // Add a defensive check to ensure required data is present.
        if (is_null($task->assignee) || is_null($task->actual_completion_date)) {
            return false;
        }

        // Parse the due date and set the time to the end of the day (23:59:59)
        // in the assignee's timezone.
        $dueDate = Carbon::parse($task->due_date, $task->assignee->timezone)->endOfDay();

        // Correctly parse the UTC timestamp from the database and convert it to the user's timezone.
        $completedDate = Carbon::parse($task->actual_completion_date)->setTimezone($task->assignee->timezone ?? 'Asia/Karachi');

        // A task is on time if the completed date is on or before the end of the due date.
        return $completedDate->lte($dueDate);
    }

    /**
     * Determines if a task was completed at least 24 hours before its due date.
     *
     * The check is timezone-aware, based on the task assignee's timezone.
     *
     * @param Task $task
     * @return bool
     */
    public function isTaskEarly(Task $task): bool
    {
        // Add a defensive check to ensure required data is present.
        if (is_null($task->assignee) || is_null($task->actual_completion_date)) {
            return false;
        }

        // Get the task's due date and completed date in the assignee's timezone.
        // The deadline for "early" completion is 24 hours before the end of the due date.
        $earlyDeadline = Carbon::parse($task->due_date, $task->assignee->timezone)->endOfDay()->subDay();

        // Correctly parse the UTC timestamp from the database and convert it to the user's timezone.
        $completedDate = Carbon::parse($task->actual_completion_date)->setTimezone($task->assignee?->timezone ?? 'Asia/Karachi');

        // A task is early if the completed date is on or before the early deadline.
        return $completedDate->lte($earlyDeadline);
    }
}
