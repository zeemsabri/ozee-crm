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
use App\Models\Email;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PointsService
{
    private const BASE_POINTS_EMAIL_SENT = 50;
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

        if(!$project) {
            return null;
        }

        $multiplier = $project ? ($project->projectTier->point_multiplier ?? 1.0) : 1.0;
        $finalPoints = $basePoints * $multiplier;

        // Log the final awarded points only if status is not denied
        $pointsToRecord = ($status === 'denied') ? 0 : $finalPoints;

        if(!$userId) {
            return null;
        }

        $data = [
            'user_id' => $userId,
            'project_id' => $projectId,
            'points_awarded' => $pointsToRecord,
            'description' => $description,
            'pointable_id' => $pointable->id ?? null,
            'pointable_type' => $pointable ? get_class($pointable) : null,
            'status' => $status
        ];

        Log::info(json_encode($data));;
        $ledgerEntry = PointsLedger::create($data);

        // Only update monthly points if the entry is not a denial
        if ($status !== 'denied') {
            $this->updateMonthlyPoints($userId, $finalPoints, Carbon::now()->year, Carbon::now()->month);
        }

        return $ledgerEntry;
    }

    /**
     * Get the current time for a user, adjusted to their timezone.
     * Handles cases where the user's timezone is not set.
     * @param User $user
     * @return Carbon
     */
    private function getUserCarbonTimezone(User $user)
    {
        // Use the user's timezone if it exists, otherwise default to 'Asia/Karachi'.
        $timezone = $user->timezone ?? 'Asia/Karachi';
        return Carbon::now($timezone);
    }

    /**
     * Awards points for a specific pointable model, updating or creating a ledger entry.
     *
     * @param object $pointable
     * @return PointsLedger|null
     */
    public function recalculateAndAwardPoints(object $pointable)
    {
        // Determine the type of object and call the appropriate logic
        switch (get_class($pointable)) {
            case Standup::class:
                return $this->awardStandupPoints($pointable);
            case Task::class:
                return $this->awardTaskPoints($pointable);
            case Milestone::class:
                if ($pointable->status === 'completed') {
                    // This assumes a separate way to determine on-time vs. late for milestones
                    // We'll award on-time points here as an example
                    return $this->awardMilestoneOnTimePoints($pointable->user_id, $pointable);
                } else {
                    return $this->awardMilestoneLatePoints($pointable->user_id, $pointable);
                }
            case Kudo::class:
                return $this->awardKudosPoints($pointable);
            case Email::class:
                return $this->awardEmailSentPoints($pointable);
            default:
                Log::warning('Unsupported pointable model for recalculation: ' . get_class($pointable));
                return null;
        }
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
            return null;
        }

        $userTime = $this->getUserCarbonTimezone($user);
        $userTimezone = $user->timezone ?? 'Asia/Karachi';

        // Corrected Deduplication Check: Check for a previous standup on the same day for the same user.
        $existingStandupPoints = PointsLedger::where('user_id', $standup->creator_id)
            ->where('pointable_type', Standup::class)
            ->where('project_id', $standup->project_id)
            ->whereDate('created_at', $userTime->toDateString())
            ->exists();

        if ($existingStandupPoints) {
            Log::info('Points already awarded for a standup on this day for this user.');
            $this->awardPoints(
                $standup->creator_id,
                $standup->project_id,
                0,
                'Duplicate Daily Standup - Points not awarded for ' . $userTime->toDateString(),
                $standup,
                'denied'
            );
            return null;
        }

        $standupTimeInUserTimezone = $standup->created_at->setTimezone($userTimezone);
        $deadline = $userTime->copy()->setTime(11, 0, 0);

        $isLate = $standupTimeInUserTimezone->gt($deadline);
        $points = $isLate ? self::BASE_POINTS_STANDUP_LATE : self::BASE_POINTS_STANDUP_ON_TIME;
        $description = $isLate ? "Late Daily Standup on " . $userTime->toDateString() . " - submitted at " . $standupTimeInUserTimezone->format('H:i:s') : "On-Time Daily Standup on " . $userTime->toDateString();

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
            $this->awardPoints(
                $userId,
                $milestone->project_id,
                0,
                'Points already awarded for milestone: ' . $milestone->name,
                $milestone,
                'denied'
            );
            return;
        }

        $this->awardPoints(
            $userId,
            $milestone->project_id,
            self::BASE_POINTS_MILESTONE_ON_TIME,
            'On-Time Milestone Completion: ' . $milestone->name,
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
            $this->awardPoints(
                $userId,
                $milestone->project_id,
                0,
                'Points already awarded for milestone: ' . $milestone->name,
                $milestone,
                'denied'
            );
            return;
        }

        $this->awardPoints(
            $userId,
            $milestone->project_id,
            self::BASE_POINTS_MILESTONE_LATE,
            'Late Milestone Completion: ' . $milestone->name,
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
            $this->awardPoints(
                $task->assigned_to_user_id,
                $task->project_id,
                0,
                'Points already awarded for task: ' . $task->name,
                $task,
                'denied'
            );
            return;
        }

        $user = User::find($task->assigned_to_user_id);
        if (!$user) {
            Log::info('User not found for task.');
            return;
        }
        $userTime = $this->getUserCarbonTimezone($user);
        $userTimezone = $user->timezone ?? 'Asia/Karachi';

        // Convert due date to user's timezone for comparison
        $dueDateInUserTimezone = Carbon::parse($task->due_date)->setTimezone($userTimezone);

        $completedAt = $task->actual_completion_date ? Carbon::parse($task->actual_completion_date)->setTimezone($userTimezone) : $userTime;

        $standupOnDueDate = Standup::where('creator_id', $task->assigned_to_user_id)
            ->where('type', ProjectNote::STANDUP)
            ->whereDate('created_at', $completedAt->toDateString())
            ->first();

        if (!$standupOnDueDate) {
            Log::info('No standup found for task completion for ' . $completedAt->toDateString());
            $this->awardPoints(
                $task->assigned_to_user_id,
                $task->project_id,
                0,
                'Task completion on ' . $completedAt->toDateString() . ' denied: No standup found for that day.',
                $task,
                'denied'
            );
            return;
        }

        // Check for early completion (at least 24 hours before the due date)
        $dueBefore24Hours = $dueDateInUserTimezone->copy()->subDay();

        if ($completedAt->lte($dueBefore24Hours)) {
            $points = self::BASE_POINTS_TASK_EARLY;
            $description = 'Early Task Completion: ' . $task->name . ' on ' . $completedAt->toDateString();
        } elseif ($completedAt->lte($dueDateInUserTimezone->endOfDay())) {
            $points = self::BASE_POINTS_TASK_ON_TIME;
            $description = 'On-Time Task Completion: ' . $task->name . ' on ' . $completedAt->toDateString();
        } else {
            Log::info('Task completion date is after due date.');
            $this->awardPoints(
                $task->assigned_to_user_id,
                $task->project_id,
                0,
                'Task completion denied: ' . $task->name . ' was completed after due date ' . $dueDateInUserTimezone->toDateString(),
                $task,
                'denied'
            );
            return;
        }

        $standupTimeInUserTimezone = $standupOnDueDate->created_at->setTimezone($userTimezone);
        $standupDeadline = $completedAt->copy()->setTime(11, 0, 0);

        if ($standupTimeInUserTimezone->gt($standupDeadline)) {
            $points *= 0.75;
            $description .= ' (Reduced due to late standup)';
        }

        $this->awardPoints(
            $task->assigned_to_user_id,
            $task->project_id,
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
            $this->awardPoints(
                $kudo->recipient_id,
                $kudo->project_id,
                0,
                'Duplicate Kudo entry for: ' . $kudo->comment,
                $kudo,
                'denied'
            );
            return;
        }

        Log::info('awarding');
        if ($kudo->is_approved) {
            $this->awardPoints(
                $kudo->recipient_id,
                $kudo->project_id,
                self::BASE_POINTS_KUDOS,
                'Peer Kudos (Approved): ' . $kudo->comment,
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
            $this->awardPoints(
                $userId,
                $projectId,
                0,
                'Duplicate Meeting Punctuality for Meeting ID: ' . $meetingId,
                (object)['id' => $meetingId],
                'denied'
            );
            return;
        }

        $this->awardPoints(
            $userId,
            $projectId,
            self::BASE_POINTS_MEETING_ON_TIME,
            'On-Time Meeting Punctuality for Meeting ID: ' . $meetingId,
            (object)['id' => $meetingId],
            'paid',
            ['meeting_id' => $meetingId]
        );
    }

    /**
     * Awards points to the sender when an email is sent.
     * Conditions: email.type == 'sent' and email.status == 'sent'.
     * Additional condition: project's last_email_received must be within the last 4 hours.
     * Deduplication: one award per email id.
     *
     * @param Email $email
     * @return void
     */
    public function awardEmailSentPoints(Email $email)
    {
        // Ensure conditions are met
        if (strtolower($email->type ?? '') !== 'sent' || strtolower($email->status ?? '') !== 'sent') {
            return;
        }

        // Prevent duplicate awards for the same email
        if (PointsLedger::where('pointable_id', $email->id)->where('pointable_type', Email::class)->exists()) {
            Log::info('Points already awarded for this email.');
            $this->awardPoints(
                $email->sender->id,
                $email->conversation?->project?->id,
                0,
                'Duplicate Email Points for Email ID: ' . $email->id,
                $email,
                'denied'
            );
            return;
        }

        // Ensure the sender is a User (we only award user points)
        $sender = $email->sender;
        if (!$sender || !($sender instanceof User)) {
            Log::info('Email sender is not a User or missing; skipping points.');
            return;
        }

        // Fetch the associated project via the conversation
        $project = optional($email->conversation)->project;
        if (!$project) {
            Log::info('No project associated with email; skipping points.', ['email_id' => $email->id]);
            return;
        }

        // Ensure we have a recent last_email_received (from client)
        $lastReceived = $project->last_email_received; // cast to Carbon by model
        if (!$lastReceived) {
            Log::info('Project last_email_received is null; skipping email points.', ['project_id' => $project->id ?? null, 'email_id' => $email->id]);
            return;
        }

        // Determine the sending time to compare against (prefer sent_at, fallback to updated_at, finally now)
        $sentAt = $email->sent_at ?? $email->updated_at ?? Carbon::now();
        if ($sentAt instanceof \DateTimeInterface === false) {
            $sentAt = Carbon::parse($sentAt);
        }

        // Award only if the reply (sentAt) is within 4 hours of the last received time
        $withinFourHours = $lastReceived->copy()->addHours(4)->gte($sentAt);
        if (!$withinFourHours) {
            $this->awardPoints(
                $sender->id,
                $project->id,
                0,
                'Email Points Denied: Reply to client email on ' . $lastReceived->toDateString() . ' not within 4 hours',
                $email,
                'denied'
            );
            return;
        }

        $projectId = $project->id;

        $this->awardPoints(
            $sender->id,
            $projectId,
            self::BASE_POINTS_EMAIL_SENT,
            'Email Sent (within 4h of client message) for ' . $email->subject,
            $email,
            PointsLedger::STATUS_PAID,
            ['email_id' => $email->id]
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
        if (!$user) {
            Log::info('User not found for weekly streak check.');
            return;
        }
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
