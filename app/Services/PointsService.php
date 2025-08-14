<?php

namespace App\Services;

use App\Models\Kudo;
use App\Models\Milestone;
use App\Models\PointsLedger;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Task;
use App\Models\User;
use App\Models\ProjectNote as Standup;
use App\Models\MonthlyPoint;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PointsService
{
    private const BASE_POINTS_STANDUP_ON_TIME = 25;
    private const BASE_POINTS_STANDUP_LATE = 10;
    private const BASE_POINTS_TASK_ON_TIME = 50;
    private const BASE_POINTS_TASK_EARLY = 100;
    private const BASE_POINTS_MEETING_ON_TIME = 25;
    private const BASE_POINTS_KUDOS = 25;
    private const WEEKLY_STREAK_BONUS = 100;
    private const BASE_POINTS_MILESTONE_ON_TIME = 500; // New constant
    private const BASE_POINTS_MILESTONE_LATE = 100; // New constant

    /**
     * Awards points for a user action and logs it in the points ledger.
     *
     * @param string $userId
     * @param string|null $projectId
     * @param float $basePoints
     * @param string $description
     * @param object|null $pointable
     * @param string $status
     * @param array $extraData
     * @return PointsLedger|null
     */
    private function awardPoints($userId, $projectId, $basePoints, $description, $pointable, $status = 'paid', $extraData = [])
    {
        $project = $projectId ? Project::find($projectId) : null;
        if ($projectId && !$project) {
            Log::info('Project not found');
            return null;
        }

        $multiplier = $project ? ($project->projectTier->point_multiplier ?? 1.0) : 1.0;
        $finalPoints = $basePoints * $multiplier;

        $data = [
            'user_id' => $userId,
            'project_id' => $projectId,
            'points_awarded' => $finalPoints,
            'description' => $description,
            'pointable_id' => $pointable->id ?? null,
            'pointable_type' => $pointable ? get_class($pointable) : null,
            'status' => $status
        ];
        Log::info(json_encode($data));;
        $ledgerEntry = PointsLedger::create($data);

        $this->updateMonthlyPoints($userId, $finalPoints, Carbon::now()->year, Carbon::now()->month);

        return $ledgerEntry;
    }

    /**
     * Get the current time for a user, adjusted to their timezone.
     * @param User $user
     * @return Carbon
     */
    private function getUserCarbonTimezone(User $user)
    {
        $timezone = $user->timezone ?? config('app.timezone');
        return Carbon::now($timezone);
    }

    /**
     * Calculates and awards points for a daily standup.
     *
     * @param Standup $standup
     */
    public function awardStandupPoints(Standup $standup)
    {
        $user = User::find($standup->creator_id);
        if (!$user) {
            Log::info('User not found for standup.');
            return;
        }

        $userTime = $this->getUserCarbonTimezone($user);

        // Corrected Deduplication Check: Check for a previous standup on the same day for the same user.
        $existingStandupPoints = PointsLedger::where('user_id', $standup->creator_id)
            ->where('pointable_type', Standup::class)
            ->whereDate('created_at', $userTime->toDateString())
            ->exists();

        if ($existingStandupPoints) {
            Log::info('Points already awarded for a standup on this day for this user.');
            return;
        }

        $standupTimeInUserTimezone = $standup->created_at->setTimezone($user->timezone);
        $deadline = $userTime->copy()->setTime(11, 0, 0);

        $isLate = $standupTimeInUserTimezone->gt($deadline);
        $points = $isLate ? self::BASE_POINTS_STANDUP_LATE : self::BASE_POINTS_STANDUP_ON_TIME;
        $description = $isLate ? 'Late Daily Standup' : 'On-Time Daily Standup';

        Log::info('points: '. $points . ' to: ' . $standup->creator_id);
        $this->awardPoints(
            $standup->creator_id,
            $standup->project_id,
            $points,
            $description,
            $standup,
            'paid',
            ['standup_date' => $standup->created_at->toDateString()]
        );

        $this->checkForWeeklyStreak($standup->user_id);
    }

    /**
     * Awards points to a user for an on-time milestone completion.
     * @param int $userId
     * @param Milestone $milestone
     */
    public function awardMilestoneOnTimePoints($userId, Milestone $milestone)
    {
        // Deduplication check
        if (PointsLedger::where('user_id', $userId)->where('pointable_id', $milestone->id)->where('pointable_type', Milestone::class)->exists()) {
            Log::info('Points already awarded for this milestone.');
            return;
        }

        $this->awardPoints(
            $userId,
            $milestone->project_id,
            self::BASE_POINTS_MILESTONE_ON_TIME,
            'On-Time Milestone Completion',
            $milestone
        );
    }

    /**
     * Awards points to a user for a late milestone completion.
     * @param int $userId
     * @param Milestone $milestone
     */
    public function awardMilestoneLatePoints($userId, Milestone $milestone)
    {
        // Deduplication check
        if (PointsLedger::where('user_id', $userId)->where('pointable_id', $milestone->id)->where('pointable_type', Milestone::class)->exists()) {
            Log::info('Points already awarded for this milestone.');
            return;
        }

        $this->awardPoints(
            $userId,
            $milestone->project_id,
            self::BASE_POINTS_MILESTONE_LATE,
            'Late Milestone Completion',
            $milestone
        );
    }


    /**
     * Calculates and awards points for task completion.
     *
     * @param Task $task
     * @param Milestone|null $milestone
     */
    public function awardTaskPoints(Task $task, Milestone|Null $milestone = null)
    {
        if (PointsLedger::where('pointable_id', $task->id)->where('pointable_type', Task::class)->exists()) {
            Log::info('Points already awarded for this task completion.');
            return;
        }

        $user = User::find($task->assigned_to_user_id);
        if (!$user) {
            Log::info('User not found for task.');
            return;
        }
        $userTime = $this->getUserCarbonTimezone($user);

        // Convert due date to user's timezone for comparison
        $dueDateInUserTimezone = Carbon::parse($task->due_date)->setTimezone($user->timezone ?? config('app.timezone'));

        $completedAt = $task->actual_completion_date ? Carbon::parse($task->actual_completion_date)->setTimezone($user->timezone) : $userTime;

        $standupOnDueDate = Standup::where('creator_id', $task->assigned_to_user_id)
            ->where('type', ProjectNote::STANDUP)
            ->whereDate('created_at', $completedAt->toDateString())
            ->first();

        if (!$standupOnDueDate) {
            Log::info('No standup found for task completion for ' . $completedAt->toDateString());
            return;
        }

        // Check for early completion (at least 24 hours before the due date)
        $dueBefore24Hours = $dueDateInUserTimezone->copy()->subDay();

        if ($completedAt->lte($dueBefore24Hours)) {
            $points = self::BASE_POINTS_TASK_EARLY;
            $description = 'Early Task Completion';
        } elseif ($completedAt->lte($dueDateInUserTimezone->endOfDay())) {
            $points = self::BASE_POINTS_TASK_ON_TIME;
            $description = 'On-Time Task Completion';
        } else {
            Log::info('Task completion date is after due date.');
            return;
        }

        $standupTimeInUserTimezone = $standupOnDueDate->created_at->setTimezone($user->timezone);
        $standupDeadline = $completedAt->copy()->setTime(11, 0, 0);

        if ($standupTimeInUserTimezone->gt($standupDeadline)) {
            $points *= 0.75;
            $description .= ' (Reduced due to late standup)';
        }

        $this->awardPoints(
            $task->assigned_to_user_id,
            $task->milestone->project_id,
            $points,
            $description,
            $task,
            'paid',
            ['completion_date' => $completedAt->toDateString()]
        );
    }

    /**
     * Awards points to a user for an on-time milestone completion.
     * @param int $userId
     * @param Milestone $milestone
     */
    public function awardKudosPoints(Kudo $kudo)
    {
        if (PointsLedger::where('pointable_id', $kudo->id)->where('pointable_type', Kudo::class)->exists()) {
            Log::info('Points already awarded for this kudo.');
            return;
        }

        Log::info('awarding');
        if ($kudo->is_approved) {
            $this->awardPoints(
                $kudo->recipient_id,
                $kudo->project_id,
                self::BASE_POINTS_KUDOS,
                'Peer Kudos (Approved)',
                $kudo,
                'pending',
                ['comment' => $kudo->comment]
            );
        }
    }

    /**
     * Awards points for on-time meeting attendance.
     * This method needs an identifier for the meeting itself to prevent duplicates.
     *
     * @param string $userId
     * @param string $projectId
     * @param string $meetingId
     */
    public function awardMeetingPunctuality($userId, $projectId, $meetingId)
    {
        if (PointsLedger::where('pointable_id', $meetingId)->where('description', 'On-Time Meeting Punctuality')->exists()) {
            Log::info('Points already awarded for this meeting.');
            return;
        }

        $this->awardPoints(
            $userId,
            $projectId,
            self::BASE_POINTS_MEETING_ON_TIME,
            'On-Time Meeting Punctuality',
            (object)['id' => $meetingId],
            'paid',
            ['meeting_id' => $meetingId]
        );
    }

    /**
     * Checks for a weekly standup streak and awards a bonus if successful.
     *
     * @param string $userId
     */
    private function checkForWeeklyStreak($userId)
    {
        $user = User::find($userId);
        $userTime = $this->getUserCarbonTimezone($user);

        $startOfWeek = $userTime->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $userTime->copy()->endOfWeek(Carbon::FRIDAY);

        // Count on-time standups for the current week
        $onTimeStandups = Standup::where('creator_id', $userId)
            ->whereDate('created_at', '>=', $startOfWeek)
            ->whereDate('created_at', '<=', $endOfWeek)
            ->whereRaw("TIME(created_at) <= '11:00:00'")
            ->count();

        if ($onTimeStandups === 5 && !PointsLedger::where('user_id', $userId)
                ->where('description', 'Weekly Standup Streak Bonus')
                ->whereDate('created_at', '>=', $startOfWeek)
                ->exists()) {

            $this->awardPoints(
                $userId,
                null,
                self::WEEKLY_STREAK_BONUS,
                'Weekly Standup Streak Bonus',
                null
            );
        }
    }

    /**
     * Updates the monthly points for a user after a points ledger entry.
     *
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
