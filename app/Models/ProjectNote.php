<?php

namespace App\Models;

use App\Events\StandupSubmittedEvent;
use App\Models\Traits\HasUserTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Models\Traits\Taggable;

class ProjectNote extends Model
{

    protected $casts = [
        'context' => 'array',
    ];

    use HasUserTimezone;
    /**
     * Create a project note (optionally attached to a noteable) and send to Google Chat automatically.
     *
     * @param \App\Models\Project $project
     * @param string $content
     * @param array $options ['type' => string, 'noteable' => Model|null]
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

        // Try sending to Google Chat if space available
        if ($project->google_chat_id) {
            try {
                $user = Auth::user();
                $prefix = '📝';
                if ($type === 'standup') $prefix = '🏃‍♂️';
                if ($type === 'milestone') $prefix = '📌';
                $messageText = "$prefix *{$user?->name}*: " . $content;
                $chatService = app(\App\Services\GoogleChatService::class);
                $response = $chatService->sendMessage($project->google_chat_id, $messageText);
                $createdNote->chat_message_id = $response['name'] ?? null;
                $createdNote->save();
            } catch (\Exception $e) {
                \Log::error('Failed to send note to Google Chat', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $createdNote;
    }
    use HasFactory, Taggable;

    const STANDUP = 'standup';
    const KUDOS = 'kudos';
    const GENERAL = 'general';

    const COMMENT = 'comment';
    const TYPES = [
        self::STANDUP,
        self::KUDOS,
        self::GENERAL,
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

            // Fallback: If no creator is identified, you might want to log, throw an error,
            // or assign a default (e.g., an 'admin' user or null if nullable).
            // For now, if no creator, it remains unset, allowing database to handle nullability.

        });

        // Dispatch the standup event after the note has been created so it has a persisted ID
        static::created(function (ProjectNote $note) {
            // Only dispatch for standup notes (listener also guards, but this avoids unnecessary jobs)
            if ($note->type === self::STANDUP && $note->creator_type === User::class) {
                StandupSubmittedEvent::dispatch($note);
            }
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
        }
        catch (\Exception $e) {
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
}
