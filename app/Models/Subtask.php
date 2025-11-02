<?php

namespace App\Models;

use App\Enums\SubtaskStatus;
use App\Services\GoogleChatService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Subtask extends Model
{
    use HasFactory;

    // Subtask status constants (aliases maintained for backward compatibility)
    /** @deprecated use App\Enums\SubtaskStatus::ToDo */
    public const STATUS_TO_DO = \App\Enums\SubtaskStatus::ToDo->value;

    /** @deprecated use App\Enums\SubtaskStatus::InProgress */
    public const STATUS_IN_PROGRESS = \App\Enums\SubtaskStatus::InProgress->value;

    /** @deprecated use App\Enums\SubtaskStatus::Done */
    public const STATUS_DONE = \App\Enums\SubtaskStatus::Done->value;

    /** @deprecated use App\Enums\SubtaskStatus::Blocked */
    public const STATUS_BLOCKED = \App\Enums\SubtaskStatus::Blocked->value;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'assigned_to_user_id',
        'due_date',
        'actual_completion_date',
        'status',
        'parent_task_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'due_date' => 'date',
        'actual_completion_date' => 'date',
        'status' => \App\Casts\MilestoneStatusCast::class.':'.\App\Enums\SubtaskStatus::class,
    ];

    /**
     * Get the parent task of this subtask.
     */
    public function parentTask()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /**
     * Get the user assigned to this subtask.
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Check if the subtask is completed.
     *
     * @return bool
     */
    public function isCompleted()
    {
        $status = $this->status instanceof SubtaskStatus ? $this->status->value : (string) $this->status;

        return $status === SubtaskStatus::Done->value;
    }

    /**
     * Check if the subtask is overdue.
     *
     * @return bool
     */
    public function isOverdue()
    {
        return $this->due_date && $this->due_date->isPast() && ! $this->isCompleted();
    }

    /**
     * Mark the subtask as completed.
     *
     * @return void
     */
    public function markAsCompleted()
    {
        $this->status = SubtaskStatus::Done;
        $this->actual_completion_date = now();
        $this->save();
    }

    /**
     * Start the subtask (change status to In Progress).
     *
     * @return void
     */
    public function start()
    {
        $this->status = SubtaskStatus::InProgress;
        $this->save();
    }

    /**
     * Block the subtask (change status to Blocked).
     *
     * @return void
     */
    public function block()
    {
        $this->status = SubtaskStatus::Blocked;
        $this->save();
    }

    /**
     * Add a note to the parent task's Google Chat space, indicating it's for this subtask.
     *
     * @return array|null
     */
    public function addNote(string $note, User $user)
    {
        // Load the parent task if not already loaded
        if (! $this->relationLoaded('parentTask')) {
            $this->load('parentTask');
        }

        if (! $this->parentTask || ! $this->parentTask->google_chat_space_id) {
            Log::error('Cannot add note to subtask: Parent task or Google Chat space ID is missing', [
                'subtask_id' => $this->id,
                'parent_task_id' => $this->parent_task_id,
            ]);

            return null;
        }

        try {
            $chatService = new GoogleChatService;

            // Format the message to clearly indicate it's for a subtask
            $message = "📌 *Subtask: {$this->name}*\n{$user->name}: {$note}";

            // If the parent task has a thread, use it; otherwise, create a new message
            if (isset($this->parentTask->google_chat_thread_id)) {
                $result = $chatService->sendThreadedMessage(
                    $this->parentTask->google_chat_space_id,
                    $this->parentTask->google_chat_thread_id,
                    $message
                );
            } else {
                $result = $chatService->sendMessage(
                    $this->parentTask->google_chat_space_id,
                    $message
                );
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to add note to subtask: '.$e->getMessage(), [
                'subtask_id' => $this->id,
                'parent_task_id' => $this->parent_task_id,
                'exception' => $e,
            ]);

            return null;
        }
    }
}
