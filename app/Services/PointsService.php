<?php

namespace App\Services;

use App\Models\Kudo;
use App\Models\PointsLedger;
use App\Models\Project;
use App\Models\Task;
use App\Models\ProjectNote as Standup;
use App\Models\MonthlyPoint;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PointsService
{
    // Base points for different actions
    private const BASE_POINTS_STANDUP_ON_TIME = 25;
    private const BASE_POINTS_STANDUP_LATE = 10;
    private const BASE_POINTS_TASK_ON_TIME = 50;
    private const BASE_POINTS_TASK_EARLY = 100;
    private const BASE_POINTS_MEETING_ON_TIME = 25;
    private const BASE_POINTS_KUDOS = 25;
    private const WEEKLY_STREAK_BONUS = 100;

    /**
     * Awards points for a user action and logs it in the points ledger.
     *
     * @param string $userId The ID of the user.
     * @param string $projectId The ID of the project.
     * @param float $basePoints The base points to be awarded.
     * @param string $description A description of the points awarded.
     * @param object $pointable The model instance associated with the points (e.g., Standup, Task).
     * @return PointsLedger
     */
    private function awardPoints($userId, $projectId, $basePoints, $description, $pointable)
    {
        $project = Project::find($projectId);
        if (!$project) {
            // Handle project not found, perhaps throw an exception or return null
            return null;
        }

        $multiplier = $project->projectTier->point_multiplier ?? 1.0;
        $finalPoints = $basePoints * $multiplier;

        return PointsLedger::create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'points_awarded' => $finalPoints,
            'description' => $description,
            'pointable_id' => $pointable->id,
            'pointable_type' => get_class($pointable),
        ]);
    }

    /**
     * Calculates and awards points for a daily standup.
     *
     * @param Standup $standup
     */
    public function awardStandupPoints(Standup $standup)
    {
        $isLate = $standup->created_at->gt(Carbon::today()->setTime(11, 0, 0));
        $points = $isLate ? self::BASE_POINTS_STANDUP_LATE : self::BASE_POINTS_STANDUP_ON_TIME;
        $description = $isLate ? 'Late Daily Standup' : 'On-Time Daily Standup';

        $this->awardPoints($standup->user_id, $standup->project_id, $points, $description, $standup);

        // Check for weekly streak bonus
        $this->checkForWeeklyStreak($standup->user_id);
    }

    /**
     * Calculates and awards points for task completion.
     *
     * @param Task $task
     */
    public function awardTaskPoints(Task $task)
    {
        // Check if a standup was submitted on the task's due date
        $standupOnDueDate = Standup::where('user_id', $task->assigned_to)
            ->whereDate('standup_date', $task->due_date)
            ->first();

        if (!$standupOnDueDate) {
            return;
        }

        // Calculate points based on completion time
        $completionDate = $task->completion_date ?? Carbon::now();
        $dueBefore24Hours = Carbon::parse($task->due_date)->subDay();

        if ($completionDate->lte($dueBefore24Hours)) {
            $points = self::BASE_POINTS_TASK_EARLY;
            $description = 'Early Task Completion';
        } elseif ($completionDate->lte(Carbon::parse($task->due_date)->endOfDay())) {
            $points = self::BASE_POINTS_TASK_ON_TIME;
            $description = 'On-Time Task Completion';
        } else {
            return; // No points for late completion
        }

        // Apply a 75% point value if standup was late
        if ($standupOnDueDate->is_late) {
            $points *= 0.75;
            $description .= ' (Reduced due to late standup)';
        }

        $this->awardPoints($task->assigned_to, $task->project_id, $points, $description, $task);
    }

    /**
     * Awards points for a manager-approved kudo.
     *
     * @param Kudo $kudo
     */
    public function awardKudosPoints(Kudo $kudo)
    {
        if ($kudo->is_approved) {
            $this->awardPoints(
                $kudo->recipient_id,
                $kudo->project_id,
                self::BASE_POINTS_KUDOS,
                'Peer Kudos (Approved)',
                $kudo
            );
        }
    }

    /**
     * Awards points for on-time meeting attendance.
     *
     * @param string $userId
     * @param string $projectId
     */
    public function awardMeetingPunctuality($userId, $projectId)
    {
        $this->awardPoints(
            $userId,
            $projectId,
            self::BASE_POINTS_MEETING_ON_TIME,
            'On-Time Meeting Punctuality',
            null
        );
    }

    /**
     * Checks for a weekly standup streak and awards a bonus if successful.
     *
     * @param string $userId
     */
    private function checkForWeeklyStreak($userId)
    {
        // Define the start and end of the current week (Monday to Friday)
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $endOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->addDays(4)->format('Y-m-d');

        // Count on-time standups for the current week
        $onTimeStandups = Standup::where('user_id', $userId)
            ->whereDate('standup_date', '>=', $startOfWeek)
            ->whereDate('standup_date', '<=', $endOfWeek)
            ->whereRaw('DATE(created_at) = standup_date')
            ->whereRaw("TIME(created_at) <= '11:00:00'")
            ->count();

        // If they have 5 on-time standups and haven't received the bonus yet for this week
        if ($onTimeStandups === 5 && !PointsLedger::where('user_id', $userId)
                ->where('description', 'Weekly Standup Streak Bonus')
                ->whereDate('created_at', '>=', $startOfWeek)
                ->exists()) {

            // Award the bonus. Note: This assumes a project context, a general '0' project ID could be used if not project-specific.
            $this->awardPoints(
                $userId,
                null, // No specific project, might need a generic one
                self::WEEKLY_STREAK_BONUS,
                'Weekly Standup Streak Bonus',
                null
            );
        }
    }

    /**
     * Updates the monthly points for a user after a points ledger entry.
     * This method is a key part of the real-time scoring system.
     * @param int $userId
     * @param float $points
     * @param int $year
     * @param int $month
     */
    public function updateMonthlyPoints($userId, $points, $year, $month)
    {
        $monthlyPoints = MonthlyPoint::firstOrCreate(
            ['user_id' => $userId, 'year' => $year, 'month' => $month],
            ['total_points' => 0]
        );

        $monthlyPoints->increment('total_points', $points);
    }
}
