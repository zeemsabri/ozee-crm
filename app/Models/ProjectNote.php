<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Models\Traits\Taggable;

class ProjectNote extends Model
{
    use HasFactory, Taggable;

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
