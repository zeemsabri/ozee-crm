<?php

namespace App\Models;

use App\Events\StandupSubmittedEvent;
use App\Models\Traits\HasUserTimezone;
use App\Models\Traits\Taggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProjectNote extends Model
{
    protected $casts = [
        'context' => 'array',
    ];

    use HasUserTimezone;

    /**
     * Create a project note (optionally attached to a noteable) and send to Google Chat automatically.
     *
     * @param  array  $options  ['type' => string, 'noteable' => Model|null]
     * @return static
     */
    public static function createAndNotify(Project $project, string $content, array $options = [])
    {
        $type = $options['type'] ?? 'note';
        $noteable = $options['noteable'] ?? null;

        $attributes = [
            'project_id' => $project->id,
            'content' => $content,
            'user_id' => Auth::id(),
            'type' => $type,
        ];

        if ($noteable) {
            $attributes['noteable_id'] = $noteable->getKey();
            $attributes['noteable_type'] = get_class($noteable);
        }

        /** @var ProjectNote $createdNote */
        $createdNote = static::create($attributes);

        return $createdNote;
    }

    use HasFactory, Taggable;

    const STANDUP = 'standup';

    const KUDOS = 'kudos';

    const GENERAL = 'general';

    const DAILY_SUMMARY = 'daily_summary';
    const MEETING_MINUTES = 'meeting_minutes';

    const COMMENT = 'comment';

    const TYPES = [
        self::STANDUP,
        self::KUDOS,
        self::GENERAL,
        self::MEETING_MINUTES,
        self::DAILY_SUMMARY,
        self::COMMENT
    ];

    protected $fillable = [
        'project_id',
        'content',
        'user_id',
        'chat_message_id',
        'parent_id',
        'type',
        'noteable_id',
        'noteable_type',
        'creator_id',
        'creator_type',
        'context',
    ];

    protected $appends = ['creator_name'];

    /**
     * The "booted" method of the model.
     * This is where you register model events.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function (ProjectNote $note) {
            // Get the current request instance
            $request = app(Request::class);

            // 1. Check for standard authenticated User (team member)
            if (Auth::check()) {
                $user = Auth::user();
                if ($user instanceof \App\Models\User) { // Ensure it's your User model
                    $note->creator_id = $user->id;
                    $note->creator_type = get_class($user);
                }
            }
            // 2. Check for magic link authenticated Client
            // This relies on your VerifyMagicLinkToken middleware setting these attributes
            elseif ($request->attributes->has('magic_link_email') && $request->attributes->has('magic_link_project_id')) {
                $clientEmail = $request->attributes->get('magic_link_email');
                $client = Client::where('email', $clientEmail)->first(); // Assuming email is unique for clients

                if ($client) {
                    $note->creator_id = $client->id;
                    $note->creator_type = get_class($client);
                }
            }

            // Ensure project_id is set from noteable if missing
            if (!$note->project_id && $note->noteable_id && $note->noteable_type) {
                $noteable = $note->noteable;
                if ($noteable && isset($noteable->project_id)) {
                    $note->project_id = $noteable->project_id;
                }
            }

            // Fallback: If no creator is identified, you might want to log, throw an error,
            // or assign a default (e.g., an 'admin' user or null if nullable).
            // For now, if no creator, it remains unset, allowing database to handle nullability.

        });

        // Post-creation logic
        static::created(function (ProjectNote $note) {
            // 1. Dispatch the standup event for points calculation
            if ($note->type === self::STANDUP && $note->creator_type === User::class) {
                StandupSubmittedEvent::dispatch($note);
            }

            // 2. Automated Task Creation for Comments
            if ($note->type === self::COMMENT) {
                $note->createTaskFromComment();
            }

            // 3. Google Chat Push Notification
            $note->pushToGoogleChat();
        });

    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user associated with this note (legacy relationship).
     *
     * @deprecated Use creator() instead.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the creator of the note (User or Client).
     */
    public function creator()
    {
        return $this->morphTo();
    }

    public function parent()
    {
        return $this->belongsTo(ProjectNote::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ProjectNote::class, 'parent_id');
    }

    public function isParent()
    {
        return is_null($this->parent_id);
    }

    public function replyCount()
    {
        return $this->replies()->count();
    }

    /**
     * Get the parent model (project, task, etc.) that the note belongs to.
     */
    public function noteable()
    {
        return $this->morphTo();
    }

    public function getContentAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return 'UNABLE TO READ';
        }

    }

    public function setContentAttribute($value)
    {
        $this->attributes['content'] = Crypt::encryptString($value);
    }

    public function getCreatorNameAttribute()
    {
        return $this->creator?->name;
    }

    /**
     * Get the points ledger entries for this note.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function points()
    {
        return $this->morphMany(PointsLedger::class, 'pointable');
    }

    /**
     * Context records where this note is the source (referencable).
     */
    public function contexts()
    {
        return $this->morphMany(Context::class, 'referencable');
    }

    /**
     * Automatically create a task from this comment.
     */
    public function createTaskFromComment()
    {
        try {
            $project = $this->project;
            if (!$project) {
                return null;
            }

            // Identify Support Milestone
            $milestone = $project->supportMilestone();

            // Identify Assignee: PM otherwise Admin
            $assigneeId = $project->project_manager_id ?? $project->project_admin_id;

            // Resolve or create Task Type
            $taskType = TaskType::firstOrCreate(['name' => 'New']);

            $creator = $this->creator;
            $creatorName = $creator->name ?? 'Unknown';
            $description = "Comment from {$creatorName}:\n\n{$this->content}";

            if ($this->noteable_type === Wireframe::class) {
                $description .= "\n\n🔗 Wireframe: " . ($this->noteable?->name ?? 'Wireframe');
            } elseif ($this->noteable_type === Deliverable::class) {
                $description .= "\n\n🔗 Deliverable: " . ($this->noteable?->title ?? 'Deliverable');
            }

            if ($this->context) {
                $description .= "\n📍 Context: " . $this->context;
            }

            // Create Task with Kanban-style defaults
            $task = Task::create([
                'name' => Str::limit($this->content, 50),
                'description' => $description,
                'project_id' => $project->id,
                'milestone_id' => $milestone->id,
                'assigned_to_user_id' => $assigneeId,
                'due_date' => now()->startOfDay(),
                'status' => \App\Enums\TaskStatus::ToDo,
                'priority' => 'medium',
                'task_type_id' => $taskType->id,
                'creator_id' => $this->creator_id,
                'creator_type' => $this->creator_type,
                'source' => 'wireframe', // Specifically requested by user
                'source_id' => $this->id,
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error('Failed to create task from ProjectNote comment', [
                'note_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Push the note content to Google Chat if configured.
     */
    public function pushToGoogleChat()
    {
        if ($this->chat_message_id) {
            return; // Already pushed
        }

        $project = $this->project;
        if (!$project || !$project->google_chat_id) {
            return;
        }

        try {
            $chatService = app(\App\Services\GoogleChatService::class);
            $user = $this->creator;
            $userName = $user->name ?? 'System';

            $prefix = '📝';
            $messageText = "";

            if ($this->type === self::STANDUP) {
                // Formatting similar to ProjectActionController::addStandup
                $prefix = '🏃‍♂️';
                // Note: content for standup is already formatted in ProjectActionController or here?
                // If it's from the web, it's already formatted.
                $messageText = "$prefix *Daily Standup from {$userName} - ".date('F j, Y')."*\n\n" . $this->content;
            } else {
                if ($this->type === self::COMMENT) {
                    $prefix = '💬';
                } elseif ($this->type === 'milestone') {
                    $prefix = '📌';
                }
                $messageText = "$prefix *{$userName}*: " . $this->content;
                
                if ($this->noteable_type === Wireframe::class) {
                    $messageText .= "\n\n🔗 *Wireframe*: " . ($this->noteable?->name ?? 'Wireframe');
                }
            }

            $response = $chatService->sendMessage($project->google_chat_id, $messageText);
            
            // Save chat_message_id silently to avoid triggering events again
            $this->newQuery()->where('id', $this->id)->update([
                'chat_message_id' => $response['name'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to push ProjectNote to Google Chat', [
                'note_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
