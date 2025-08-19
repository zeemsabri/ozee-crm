<?php

namespace App\Models;

use App\Events\TaskCompletedEvent;
use App\Models\Traits\Taggable;
use App\Notifications\TaskAssigned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\GoogleChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Task extends Model
{
    use HasFactory, Taggable, SoftDeletes, LogsActivity;

    // Task status constants
    public const STATUS_TO_DO = 'To Do';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_PAUSED = 'Paused';
    public const STATUS_DONE = 'Done';
    public const STATUS_BLOCKED = 'Blocked';
    public const STATUS_ARCHIVED = 'Archived';

    // List of valid task statuses
    public const STATUSES = [
        self::STATUS_TO_DO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_BLOCKED,
        self::STATUS_ARCHIVED,
    ];

    // List of active (non-final) statuses, useful for UI/filters
    public const ACTIVE_STATUSES = [
        self::STATUS_TO_DO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_PAUSED,
        self::STATUS_BLOCKED,
    ];

    /**
     * Configure the activity log options for this model
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'status', 'assigned_to_user_id', 'due_date', 'priority', 'block_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function(string $eventName) {
                return match($eventName) {
                    'created' => 'Task was created',
                    'updated' => $this->getActivityDescriptionForUpdate(),
                    'deleted' => 'Task was deleted',
                    default => $eventName
                };
            });
    }

    /**
     * Generate a more descriptive message for updates based on what changed
     */
    protected function getActivityDescriptionForUpdate(): string
    {
        $changes = $this->getDirty();

        if (isset($changes['status'])) {
            $oldStatus = $this->getOriginal('status');
            $newStatus = $changes['status'];

            if ($oldStatus === 'To Do' && $newStatus === 'In Progress') {
                return 'Task was started';
            } elseif ($oldStatus === 'In Progress' && $newStatus === 'Paused') {
                return 'Task was paused';
            } elseif ($oldStatus === 'Paused' && $newStatus === 'In Progress') {
                return 'Task was resumed';
            } elseif ($newStatus === 'Blocked') {
                $blockReason = $this->block_reason ? ": {$this->block_reason}" : '';
                return "Task was blocked{$blockReason}";
            } elseif ($oldStatus === 'Blocked' && ($newStatus === 'To Do' || $newStatus === 'In Progress')) {
                return 'Task was unblocked';
            } elseif ($newStatus === 'Done') {
                return 'Task was completed';
            } elseif ($oldStatus === 'Done' && $newStatus === 'To Do') {
                return 'Task was revised';
            } else {
                return "Task status changed from '{$oldStatus}' to '{$newStatus}'";
            }
        }

        if (isset($changes['assigned_to_user_id'])) {
            $user = User::find($changes['assigned_to_user_id']);
            $userName = $user ? $user->name : 'someone';
            return "Task was assigned to {$userName}";
        }

        if (isset($changes['priority'])) {
            $oldPriority = $this->getOriginal('priority');
            $newPriority = $changes['priority'];
            return "Task priority changed from '{$oldPriority}' to '{$newPriority}'";
        }

        if (isset($changes['due_date'])) {
            $oldDueDate = $this->getOriginal('due_date');
            $newDueDate = $changes['due_date'];
            $oldFormatted = $oldDueDate ? date('Y-m-d', strtotime($oldDueDate)) : 'none';
            $newFormatted = $newDueDate ? date('Y-m-d', strtotime($newDueDate)) : 'none';
            return "Task due date changed from '{$oldFormatted}' to '{$newFormatted}'";
        }

        if (isset($changes['block_reason']) && !isset($changes['status'])) {
            return "Task blocking reason was updated: {$this->block_reason}";
        }

        return 'Task was updated';
    }

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
        'task_type_id',
        'milestone_id',
        'google_chat_space_id',
        'google_chat_thread_id',
        'chat_message_id',
        'creator_id',
        'creator_type',
        'priority',
        'deleted_by',
        'block_reason',
        'previous_status',
        'details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'due_date' => 'date',
        'actual_completion_date' => 'date',
        'details' => 'array',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function ($task) {
            // Send a message to the project's Google Chat space when a new task is created
            try {
                // Load the milestone and project relationships
                if (!$task->milestone) {
                    Log::error('Cannot add task to Google Chat: Task has no milestone', ['task_id' => $task->id]);
                    return;
                }

                $task->load('milestone.project');

                if (!$task->milestone->project) {
                    Log::error('Cannot add task to Google Chat: Milestone has no project', ['task_id' => $task->id, 'milestone_id' => $task->milestone_id]);
                    return;
                }

                $project = $task->milestone->project;

                $project->supportMilestone();

                if (!$project->google_chat_id) {
                    Log::error('Cannot add task to Google Chat: Project has no Google Chat space', ['task_id' => $task->id, 'project_id' => $project->id]);
                    return;
                }

                $chatService = new GoogleChatService();
                $messageText = "🆕 *New Task Created*: {$task->name}\n\n";
                $messageText .= "📋 *Description*: " . ($task->description ?: 'No description provided') . "\n";
                $messageText .= "🏁 *Milestone*: {$task->milestone->name}\n";

                if ($task->assigned_to_user_id) {
                    $task->load('assignedTo');
                    if ($task->assignedTo) {
                        $messageText .= "👤 *Assigned to*: {$task->assignedTo->name}\n";
                    }
                }

                if ($task->due_date) {
                    $messageText .= "📅 *Due Date*: {$task->due_date->format('Y-m-d')}\n";
                }

                // Send the message to the project's Google Chat space
                $messageResult = $chatService->sendMessage(
                    $project->google_chat_id,
                    $messageText
                );

                // Save the Google Chat space ID to the task
                $task->google_chat_space_id = $project->google_chat_id;

                // Extract and save the thread ID from the message result
                if (isset($messageResult['name'])) {
                    // The message name format is spaces/SPACE_ID/messages/MESSAGE_ID
                    $parts = explode('/', $messageResult['name']);
                    if (count($parts) >= 4) {
                        // Save the message ID for future reference (for threading)
                        $task->chat_message_id = $messageResult['name'];

                        // Construct thread name: spaces/{space_id}/threads/{thread_key}
                        $spaceId = $parts[1];
                        $messageIdSegment = $parts[3];

                        // Extract thread key
                        $threadKey = $messageIdSegment;
                        if (str_contains($messageIdSegment, '.')) {
                            $threadKeyParts = explode('.', $messageIdSegment);
                            $threadKey = end($threadKeyParts); // Get the last part after the dot
                        }

                        $threadId = 'spaces/' . $spaceId . '/threads/' . $threadKey;
                        $task->google_chat_thread_id = $threadId;
                    }
                }

                $task->save();

                // Send notification to assigned user if task is assigned
                if ($task->assigned_to_user_id) {
                    $task->load('assignedTo');
                    if ($task->assignedTo) {
                        try {
                            $task->assignedTo->notify(new TaskAssigned($task));
                            Log::info('Task assignment notification sent to user', [
                                'task_id' => $task->id,
                                'user_id' => $task->assigned_to_user_id
                            ]);
                        } catch (\Exception $notifyException) {
                            Log::error('Failed to send task assignment notification: ' . $notifyException->getMessage(), [
                                'task_id' => $task->id,
                                'user_id' => $task->assigned_to_user_id,
                                'exception' => $notifyException
                            ]);
                        }
                    }
                }

            } catch (\Exception $e) {
                Log::error('Failed to send task message to Google Chat: ' . $e->getMessage(), [
                    'task_id' => $task->id,
                    'exception' => $e
                ]);
            }
        });

        static::creating(function (Task $task) {
            // Get the current request instance
            $request = app(Request::class);

            // 1. Check for standard authenticated User (team member)
            if (Auth::check()) {
                $user = Auth::user();
                if ($user instanceof \App\Models\User) { // Ensure it's your User model
                    $task->creator_id = $user->id;
                    $task->creator_type = get_class($user);
                }
            }
            // 2. Check for magic link authenticated Client
            // This relies on your VerifyMagicLinkToken middleware setting these attributes
            elseif ($request->attributes->has('magic_link_email') && $request->attributes->has('magic_link_project_id')) {
                $clientEmail = $request->attributes->get('magic_link_email');
                $client = Client::where('email', $clientEmail)->first(); // Assuming email is unique for clients

                if ($client) {
                    $task->creator_id = $client->id;
                    $task->creator_type = get_class($client);
                }
            }
            // Fallback: If no creator is identified, you might want to log, throw an error,
            // or assign a default (e.g., an 'admin' user or null if nullable).
            // For now, if no creator, it remains unset, allowing database to handle nullability.
        });

    }

    /**
     * Get the user assigned to this task.
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Get the task type of this task.
     */
    public function taskType()
    {
        return $this->belongsTo(TaskType::class);
    }

    /**
     * Get the milestone associated with this task.
     */
    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * Get the project deliverable associated with this task.
     */
    public function projectDeliverable()
    {
        return $this->belongsTo(ProjectDeliverable::class);
    }

    public function getProjectIdAttribute()
    {
        return $this->milestone?->project_id;
    }

    /**
     * Get the subtasks for this task.
     */
    public function subtasks()
    {
        return $this->hasMany(Subtask::class, 'parent_task_id');
    }

    // The tags() method is now provided by the Taggable trait

    /**
     * Check if the task is completed.
     *
     * @return bool
     */
    public function isCompleted()
    {
        return $this->status === self::STATUS_DONE;
    }

    /**
     * Check if the task is overdue.
     *
     * @return bool
     */
    public function isOverdue()
    {
        return $this->due_date && $this->due_date->isPast() && !$this->isCompleted();
    }

    /**
     * Mark the task as completed and send a notification to Google Chat.
     *
     * @param User|null $user The user who completed the task (defaults to null)
     * @return void
     */
    public function markAsCompleted(User $user = null)
    {
        $oldStatus = $this->status;
        $this->status = self::STATUS_DONE;
        $this->actual_completion_date = now();
        $this->save();

        // Only send notification if status actually changed
        if ($oldStatus !== self::STATUS_DONE) {

            if($this->milestone) {
                TaskCompletedEvent::dispatch($this, $this->milestone);
            }


            try {
                // Make sure we have the Google Chat space ID
                if (!$this->google_chat_space_id) {
                    // Try to get it from the project
                    $this->load('milestone.project');

                    if (!$this->milestone || !$this->milestone->project || !$this->milestone->project->google_chat_id) {
                        Log::error('Cannot send task completed notification: Google Chat space ID is missing', [
                            'task_id' => $this->id,
                            'milestone_id' => $this->milestone_id ?? 'null'
                        ]);
                        return;
                    }

                    // Set the Google Chat space ID from the project
                    $this->google_chat_space_id = $this->milestone->project->google_chat_id;
                    $this->save();
                }

                $chatService = new GoogleChatService();

                // Prepare the message
                $messageText = "✅ *Task Completed*: {$this->name}\n\n";

                if ($user) {
                    $messageText .= "👤 *Completed by*: {$user->name}\n";
                }

                // If we have a thread ID, use threaded messages
                if ($this->google_chat_thread_id) {
                    $chatService->sendThreadedMessage(
                        $this->google_chat_space_id,
                        $this->google_chat_thread_id,
                        $messageText
                    );
                } else if ($this->chat_message_id) {
                    // If we have a chat_message_id but no thread ID, try to construct the thread ID
                    $parts = explode('/', $this->chat_message_id);
                    if (count($parts) >= 4 && $parts[0] === 'spaces' && $parts[2] === 'messages') {
                        $spaceId = $parts[1];
                        $messageIdSegment = $parts[3];

                        // Extract thread key
                        $threadKey = $messageIdSegment;
                        if (str_contains($messageIdSegment, '.')) {
                            $threadKeyParts = explode('.', $messageIdSegment);
                            $threadKey = end($threadKeyParts);
                        }

                        $threadId = 'spaces/' . $spaceId . '/threads/' . $threadKey;
                        $this->google_chat_thread_id = $threadId;
                        $this->save();

                        // Now send the threaded message
                        $chatService->sendThreadedMessage(
                            $this->google_chat_space_id,
                            $this->google_chat_thread_id,
                            $messageText
                        );
                    } else {
                        // Fall back to regular message if we can't construct a thread ID
                        $chatService->sendMessage($this->google_chat_space_id, $messageText);
                    }
                } else {
                    // Fall back to regular messages if no thread ID or chat_message_id is available
                    $chatService->sendMessage($this->google_chat_space_id, $messageText);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send task completed notification: ' . $e->getMessage(), [
                    'task_id' => $this->id,
                    'exception' => $e
                ]);
            }
        }
    }

    /**
     * Start the task (change status to In Progress) and send a notification to Google Chat.
     *
     * @param User|null $user The user who started the task (defaults to null)
     * @return void
     */
    public function start(User $user = null)
    {
        $oldStatus = $this->status;
        $this->status = self::STATUS_IN_PROGRESS;
        $this->save();

        // Only send notification if status actually changed
        if ($oldStatus !== self::STATUS_IN_PROGRESS) {
            try {
                // Make sure we have the Google Chat space ID
                if (!$this->google_chat_space_id) {
                    // Try to get it from the project
                    $this->load('milestone.project');

                    if (!$this->milestone || !$this->milestone->project || !$this->milestone->project->google_chat_id) {
                        Log::error('Cannot send task started notification: Google Chat space ID is missing', [
                            'task_id' => $this->id,
                            'milestone_id' => $this->milestone_id ?? 'null'
                        ]);
                        return;
                    }

                    // Set the Google Chat space ID from the project
                    $this->google_chat_space_id = $this->milestone->project->google_chat_id;
                    $this->save();
                }

                $chatService = new GoogleChatService();

                // Prepare the message
                $messageText = "🚀 *Task Started*: {$this->name}\n\n";

                if ($user) {
                    $messageText .= "👤 *Started by*: {$user->name}\n";
                }

                // If we have a thread ID, use threaded messages
                if ($this->google_chat_thread_id) {
                    $chatService->sendThreadedMessage(
                        $this->google_chat_space_id,
                        $this->google_chat_thread_id,
                        $messageText
                    );
                } else if ($this->chat_message_id) {
                    // If we have a chat_message_id but no thread ID, try to construct the thread ID
                    $parts = explode('/', $this->chat_message_id);
                    if (count($parts) >= 4 && $parts[0] === 'spaces' && $parts[2] === 'messages') {
                        $spaceId = $parts[1];
                        $messageIdSegment = $parts[3];

                        // Extract thread key
                        $threadKey = $messageIdSegment;
                        if (str_contains($messageIdSegment, '.')) {
                            $threadKeyParts = explode('.', $messageIdSegment);
                            $threadKey = end($threadKeyParts);
                        }

                        $threadId = 'spaces/' . $spaceId . '/threads/' . $threadKey;
                        $this->google_chat_thread_id = $threadId;
                        $this->save();

                        // Now send the threaded message
                        $chatService->sendThreadedMessage(
                            $this->google_chat_space_id,
                            $this->google_chat_thread_id,
                            $messageText
                        );
                    } else {
                        // Fall back to regular message if we can't construct a thread ID
                        $chatService->sendMessage($this->google_chat_space_id, $messageText);
                    }
                } else {
                    // Fall back to regular messages if no thread ID or chat_message_id is available
                    $chatService->sendMessage($this->google_chat_space_id, $messageText);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send task started notification: ' . $e->getMessage(), [
                    'task_id' => $this->id,
                    'exception' => $e
                ]);
            }
        }
    }

    /**
     * Block the task (change status to Blocked).
     *
     * @return void
     */
    public function block()
    {
        $this->status = self::STATUS_BLOCKED;
        $this->save();
    }

    /**
     * Archive the task (change status to Archived).
     *
     * @return void
     */
    public function archive()
    {
        $this->status = self::STATUS_ARCHIVED;
        $this->save();
    }

    /**
     * Add a note to the task's thread in the project's Google Chat space.
     *
     * @param string $note
     * @param User|Client $user
     * @return ProjectNote $projectNote
     */
    public function addNote(string $note, User|Client $user)
    {
        // Load milestone and project if not already loaded
        if (!$this->relationLoaded('milestone') || ($this->milestone && !$this->milestone->relationLoaded('project'))) {
            $this->load('milestone.project');
        }

        // Get project_id from milestone if available
        $projectId = $this->milestone->project_id ?? null;

        // Save the note to the database using the polymorphic relationship
        $projectNote = $this->notes()->create([
            'content' => $note,
            'creator_id' => $user->id,
            'creator_type' => get_class($user),
            'type' => 'note',
            'project_id' => $projectId,
        ]);

        // Try to send to Google Chat if possible
        try {
            // Make sure we have the Google Chat space ID
            if (!$this->google_chat_space_id) {
                // Try to get it from the project
                if ($this->milestone && $this->milestone->project && $this->milestone->project->google_chat_id) {
                    // Set the Google Chat space ID from the project
                    $this->google_chat_space_id = $this->milestone->project->google_chat_id;
                    $this->save();
                } else {
                    // Log that we can't send to Google Chat but continue with note creation
                    Log::info('Note created but not sent to Google Chat: Google Chat space ID is missing', [
                        'task_id' => $this->id,
                        'milestone_id' => $this->milestone_id ?? 'null'
                    ]);
                    return $projectNote; // Return the note even though we couldn't send to Google Chat
                }
            }

            // Create Google Chat service and prepare message
            $chatService = new GoogleChatService();
            $messageText = "💬 *{$user->name}*: " . $note;

            // If we have a thread ID, use threaded messages
            if ($this->google_chat_thread_id) {
                $result = $chatService->sendThreadedMessage(
                    $this->google_chat_space_id,
                    $this->google_chat_thread_id,
                    $messageText
                );

                // Update the note with the chat message ID if available
                if (isset($result['name']) && isset($projectNote)) {
                    $projectNote->chat_message_id = $result['name'];
                    $projectNote->save();
                }
            } else {
                // If we have a chat_message_id but no thread ID, try to construct the thread ID
                if ($this->chat_message_id) {
                    $parts = explode('/', $this->chat_message_id);
                    if (count($parts) >= 4 && $parts[0] === 'spaces' && $parts[2] === 'messages') {
                        $spaceId = $parts[1];
                        $messageIdSegment = $parts[3];

                        // Extract thread key
                        $threadKey = $messageIdSegment;
                        if (str_contains($messageIdSegment, '.')) {
                            $threadKeyParts = explode('.', $messageIdSegment);
                            $threadKey = end($threadKeyParts);
                        }

                        $threadId = 'spaces/' . $spaceId . '/threads/' . $threadKey;
                        $this->google_chat_thread_id = $threadId;
                        $this->save();

                        // Now send the threaded message
                        $result = $chatService->sendThreadedMessage(
                            $this->google_chat_space_id,
                            $this->google_chat_thread_id,
                            $messageText
                        );

                        // Update the note with the chat message ID if available
                        if (isset($result['name']) && isset($projectNote)) {
                            $projectNote->chat_message_id = $result['name'];
                            $projectNote->save();
                        }



                    } else {
                        // Fall back to regular message if we can't construct a thread ID
                        $result = $chatService->sendMessage($this->google_chat_space_id, $messageText);

                        // Update the note with the chat message ID if available
                        if (isset($result['name']) && isset($projectNote)) {
                            $projectNote->chat_message_id = $result['name'];
                            $projectNote->save();
                        }
                    }
                } else {
                    // Fall back to regular messages if no thread ID or chat_message_id is available
                    $result = $chatService->sendMessage($this->google_chat_space_id, $messageText);

                    // Update the note with the chat message ID if available
                    if (isset($result['name']) && isset($projectNote)) {
                        $projectNote->chat_message_id = $result['name'];
                        $projectNote->save();
                    }

                    // If this is the first message, try to extract and save the thread ID and message ID
                    if (isset($result['name'])) {
                        $this->chat_message_id = $result['name'];

                        // Update the note with the chat message ID
                        if (isset($projectNote)) {
                            $projectNote->chat_message_id = $result['name'];
                            $projectNote->save();
                        }

                        $parts = explode('/', $result['name']);
                        if (count($parts) >= 4) {
                            $spaceId = $parts[1];
                            $messageIdSegment = $parts[3];

                            // Extract thread key
                            $threadKey = $messageIdSegment;
                            if (str_contains($messageIdSegment, '.')) {
                                $threadKeyParts = explode('.', $messageIdSegment);
                                $threadKey = end($threadKeyParts);
                            }

                            $threadId = 'spaces/' . $spaceId . '/threads/' . $threadKey;
                            $this->google_chat_thread_id = $threadId;
                            $this->save();
                        }
                    }
                }
            }

            return $projectNote;

        } catch (\Exception $e) {
            Log::error('Failed to add note to task: ' . $e->getMessage(), [
                'task_id' => $this->id,
                'exception' => $e
            ]);
            return null;
        }
    }

    public function notes()
    {
        return $this->morphMany(ProjectNote::class, 'noteable');
    }

    public function expendable()
    {
        return $this->morphMany(ProjectExpendable::class, 'expendable');
    }

    /**
     * Get the creator of the task (User or Client).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function creator()
    {
        return $this->morphTo();
    }



    /**
     * Get the creator name of the task.
     *
     * @return string|null
     */
    public function getCreatorName()
    {
        if ($this->creator) {
            return $this->creator->name;
        }

        return null;
    }

    // The attachTag and detachTag methods are replaced by the syncTags method in the Taggable trait

    public function files()
    {
        return $this->morphMany(\App\Models\FileAttachment::class, 'fileable');
    }
}
