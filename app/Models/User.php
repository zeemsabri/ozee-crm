<?php

namespace App\Models;

use App\Services\GoogleUserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes; // Import Collection for getClientsAttribute
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Apply a global scope to always order users by name.
     */
    protected static function booted()
    {
        static::addGlobalScope('orderByName', function (Builder $query) {
            $table = (new static)->getTable();
            $query->orderBy($table.'.name');
        });
    }

    /**
     * Polymorphic notes attached to this user (ProjectNote noteable morph).
     */
    public function notes()
    {
        return $this->morphMany(\App\Models\ProjectNote::class, 'noteable');
    }

    protected $fillable = [
        'name',
        'email',
        'chat_name',
        'password',
        'google_id',
        'google_access_token',
        'google_refresh_token',
        'google_expires_in',
        'role_id', // Foreign key to roles table
        'timezone',
        'user_type',
        'checklist',
        'notes',
    ];

    /**
     * The attributes that are not mass assignable.
     *
     * @var array<int, string>
     */
    //    protected $guarded = [
    //    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_access_token',
        'google_refresh_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role_id' => 'integer', // Cast role_id as integer
        'user_type' => 'string',
        'last_login_at' => 'datetime',
        'checklist' => 'array',
        'notes' => 'array',
    ];

    protected $with = ['role']; // Always load the role relationship

    protected $appends = ['role_data','avatar']; // Add role data to JSON

    /**
     * Get project expendable created/owned by this user.
     */
    public function projectExpendable()
    {
        return $this->hasMany(ProjectExpendable::class);
    }

    // --- Role Helper Methods ---
    public function isSuperAdmin(): bool
    {
        // Get the role directly from the relationship to avoid using the accessor
        return $this->app_role === 'super-admin';
    }

    public function isManager(): bool
    {
        // Get the role directly from the relationship to avoid using the accessor
        return $this->app_role === 'manager';
    }

    public function isEmployee(): bool
    {
        // Get the role directly from the relationship to avoid using the accessor
        return $this->app_role === 'employee';
    }

    public function isContractor(): bool
    {
        // Get the role directly from the relationship to avoid using the accessor
        return $this->app_role === 'contractor';
    }

    /**
     * Get the user's primary role.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the app role string attribute
     * This returns the role name as a string for use in places where we need to reference the role as a string
     *
     * @return string
     */
    public function getAppRoleAttribute()
    {
        return $this->role?->slug ?? 'employee';
    }

    /**
     * Assign a role to the user.
     *
     * @param  Role|int  $role
     * @return void
     */
    public function assignRole($role)
    {
        if (is_numeric($role)) {
            $role = Role::findOrFail($role);
        }

        $this->role_id = $role->id;
        $this->save();
    }

    /**
     * Remove a role from the user.
     *
     * @param  Role|int  $role
     * @return void
     */
    public function removeRole($role)
    {
        if (is_numeric($role)) {
            $role = Role::findOrFail($role);
        }

        if ($this->role_id == $role->id) {
            $this->role_id = null;
            $this->save();
        }
    }

    /**
     * Check if the user has a specific permission through their primary role.
     *
     * @param  string  $permissionSlug
     * @return bool
     */
    public function hasPermission($permissionSlug)
    {
        // First check if the user has a direct role_id relationship
        if ($this->role_id && $this->role && $this->role->hasPermission($permissionSlug)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the user has any of the given permissions through their primary role.
     *
     * @return bool
     */
    public function hasAnyPermission(array $permissionSlugs)
    {
        // Check if the user has a direct role_id relationship
        if ($this->role_id && $this->role) {
            foreach ($permissionSlugs as $permissionSlug) {
                if ($this->role->hasPermission($permissionSlug)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all permissions for the user through their primary role.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllPermissions()
    {
        $permissions = collect();

        // Check if the user has a direct role_id relationship
        if ($this->role_id && $this->role) {
            $permissions = $permissions->merge($this->role->permissions);
        }

        return $permissions->unique('id');
    }

    // A user can be assigned to many projects (via pivot table)
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_user')->withPivot('role_id');
    }

    // Dynamic accessor to get clients associated with a user's projects
    // Note: This is an accessor, accessed like $user->clients, not $user->clients()
    public function getClientsAttribute()
    {
        // For a contractor, this gets clients only for their assigned projects.
        // For other roles (Manager, Super Admin), it might include all clients
        // based on policy/query, but this method reflects assigned projects' clients.
        $clientIds = $this->projects->load('client')->pluck('client.id')->unique()->toArray();

        return Client::whereIn('id', $clientIds)->get();
    }

    // Conversations where this user is the primary contractor
    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'contractor_id');
    }

    // Emails sent by this user
    public function sentEmails()
    {
        return $this->hasMany(Email::class, 'sender_id');
    }

    // Emails approved by this user (if they are an Admin/Manager)
    public function approvedEmails()
    {
        return $this->hasMany(Email::class, 'approved_by');
    }

    /**
     * Get the tasks assigned to this user.
     */
    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to_user_id');
    }

    /**
     * Get the subtasks assigned to this user.
     */
    public function assignedSubtasks()
    {
        return $this->hasMany(Subtask::class, 'assigned_to_user_id');
    }

    /**
     * Get the task types created by this user.
     */
    public function createdTaskTypes()
    {
        return $this->hasMany(TaskType::class, 'created_by_user_id');
    }

    /**
     * Get the tags created by this user.
     */
    public function createdTags()
    {
        return $this->hasMany(Tag::class, 'created_by_user_id');
    }

    /**
     * Scope to get user's role for a specific project
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $projectId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithProjectRole($query, $projectId)
    {
        return $query->with(['projects' => function ($query) use ($projectId) {
            $query->where('projects.id', $projectId);
        }])->whereHas('projects', function ($query) use ($projectId) {
            $query->where('projects.id', $projectId);
        });
    }

    /**
     * Get the user's role for a specific project
     *
     * @param  int  $projectId
     * @return int|null
     */
    public function getRoleForProject($projectId)
    {
        $project = $this->projects()->where('projects.id', $projectId)->first();

        return $project ? $project->pivot->role_id : null;
    }

    /**
     * Get the role data for JSON serialization
     *
     * @return array
     */
    public function getRoleDataAttribute()
    {

        if ($this->role_id && $this->role) {
            return [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ];
        }

    }

    /**
     * Get the availabilities for the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function availabilities()
    {
        return $this->hasMany(UserAvailability::class);
    }

    /**
     * Get the meetings that the user is invited to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function meetings()
    {
        return $this->belongsToMany(Meeting::class, 'meeting_attendees')
            ->withPivot('notification_sent', 'notification_sent_at')
            ->withTimestamps();
    }

    /**
     * Get the bonus transactions for the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bonusTransactions()
    {
        return $this->hasMany(BonusTransaction::class);
    }

    /**
     * Get all bonus transactions of a specific type.
     *
     * @param  string  $type  The transaction type (bonus/penalty)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBonusTransactionsByType($type)
    {
        return $this->bonusTransactions()->where('type', $type)->get();
    }

    /**
     * Calculate the total bonus amount for the user.
     *
     * @param  \DateTime|null  $startDate  Optional start date for filtering
     * @param  \DateTime|null  $endDate  Optional end date for filtering
     * @param  int|null  $projectId  Optional project ID for filtering
     * @return float The total bonus amount
     */
    public function calculateTotalBonus(?\DateTime $startDate = null, ?\DateTime $endDate = null, ?int $projectId = null)
    {
        $query = $this->bonusTransactions()
            ->where('type', 'bonus')
            ->where('status', \App\Enums\BonusTransactionStatus::Processed->value);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate->format('Y-m-d'));
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate->format('Y-m-d'));
        }

        return $query->sum('amount');
    }

    /**
     * Calculate the total penalty amount for the user.
     *
     * @param  \DateTime|null  $startDate  Optional start date for filtering
     * @param  \DateTime|null  $endDate  Optional end date for filtering
     * @param  int|null  $projectId  Optional project ID for filtering
     * @return float The total penalty amount
     */
    public function calculateTotalPenalty(?\DateTime $startDate = null, ?\DateTime $endDate = null, ?int $projectId = null)
    {
        $query = $this->bonusTransactions()
            ->where('type', 'penalty')
            ->where('status', \App\Enums\BonusTransactionStatus::Processed->value);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate->format('Y-m-d'));
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate->format('Y-m-d'));
        }

        return $query->sum('amount');
    }

    /**
     * Calculate the net bonus amount (bonuses minus penalties) for the user.
     *
     * @param  \DateTime|null  $startDate  Optional start date for filtering
     * @param  \DateTime|null  $endDate  Optional end date for filtering
     * @param  int|null  $projectId  Optional project ID for filtering
     * @return float The net bonus amount
     */
    public function calculateNetBonus(?\DateTime $startDate = null, ?\DateTime $endDate = null, ?int $projectId = null)
    {
        $totalBonus = $this->calculateTotalBonus($startDate, $endDate, $projectId);
        $totalPenalty = $this->calculateTotalPenalty($startDate, $endDate, $projectId);

        return $totalBonus - $totalPenalty;
    }

    /**
     * Get a summary of bonus/penalty transactions for the user.
     *
     * @param  \DateTime|null  $startDate  Optional start date for filtering
     * @param  \DateTime|null  $endDate  Optional end date for filtering
     * @return array The summary data
     */
    public function getBonusSummary(?\DateTime $startDate = null, ?\DateTime $endDate = null)
    {
        $query = $this->bonusTransactions()
            ->where('status', \App\Enums\BonusTransactionStatus::Processed->value);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate->format('Y-m-d'));
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate->format('Y-m-d'));
        }

        $transactions = $query->with('project')->get();

        $bonusTransactions = $transactions->where('type', 'bonus');
        $penaltyTransactions = $transactions->where('type', 'penalty');

        $totalBonus = $bonusTransactions->sum('amount');
        $totalPenalty = $penaltyTransactions->sum('amount');
        $netBonus = $totalBonus - $totalPenalty;

        // Group by project
        $projectSummaries = [];
        foreach ($transactions as $transaction) {
            $projectId = $transaction->project_id;
            if (! isset($projectSummaries[$projectId])) {
                $projectSummaries[$projectId] = [
                    'project_id' => $projectId,
                    'project_name' => $transaction->project->name,
                    'total_bonus' => 0,
                    'total_penalty' => 0,
                    'net_bonus' => 0,
                    'bonus_count' => 0,
                    'penalty_count' => 0,
                ];
            }

            if ($transaction->type === 'bonus') {
                $projectSummaries[$projectId]['total_bonus'] += $transaction->amount;
                $projectSummaries[$projectId]['bonus_count']++;
            } else {
                $projectSummaries[$projectId]['total_penalty'] += $transaction->amount;
                $projectSummaries[$projectId]['penalty_count']++;
            }

            $projectSummaries[$projectId]['net_bonus'] = $projectSummaries[$projectId]['total_bonus'] - $projectSummaries[$projectId]['total_penalty'];
        }

        return [
            'total_bonus' => $totalBonus,
            'total_penalty' => $totalPenalty,
            'net_bonus' => $netBonus,
            'bonus_count' => $bonusTransactions->count(),
            'penalty_count' => $penaltyTransactions->count(),
            'project_summaries' => array_values($projectSummaries),
            'transactions' => $transactions,
        ];
    }

    public function getProjectRoleName($project)
    {
        $roleId = $this->getRoleForProject($project->id);
        if (! $roleId) {
            return 'Staff';
        }

        return Role::find($roleId)->name ?? 'Staff';

    }

    public function hasProjectPermissionOnAnyRole(array $projectIds, $permissionSlug)
    {
        foreach ($projectIds as $projectId) {
            if ($this->hasProjectPermission($projectId, $permissionSlug)) {
                $permission = Permission::where('slug', $permissionSlug)->first();

                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                    'category' => $permission->category,
                    'source' => 'application',
                ];
            }
        }

        return false;
    }

    // In your App/Models/User.php
    public function hasProjectPermission($projectId, $permissionSlug)
    {

        $projectRole = $this->getRoleForProject($projectId);

        if (! $projectRole) {
            return false;
        }

        $role = Role::find($projectRole); // Assuming getRoleForProject returns the role ID

        if (! $role) {
            return false;
        }

        return $role->permissions()->where('slug', $permissionSlug)->exists();
    }

    /**
     * Get the Google account associated with the user.
     */
    public function googleAccount()
    {
        return $this->hasOne(GoogleAccounts::class);
    }

    public function getGoogleAccessTokenAttribute()
    {
        return $this->googleAccount()->first()?->access_token;
    }

    public function getGoogleRefreshTokenAttribute()
    {
        return $this->googleAccount()->first()?->refresh_token;
    }

    public function getGoogleExpiresInAttribute()
    {
        return $this->googleAccount()->first()?->expires_in;
    }

    public function getGoogleEmailAttribute()
    {
        return $this->googleAccount()->first()?->email;
    }

    /**
     * Check if the user has valid Google credentials.
     *
     * @return bool
     */
    public function hasGoogleCredentials()
    {
        $googleAccount = $this->googleAccount()->first();

        // If no credentials exist, return false
        if (! $googleAccount) {
            return false;
        }

        // If credentials are not expired, they're valid
        if (! $googleAccount->isExpired()) {
            return true;
        }

        // If credentials are expired, try to refresh them
        try {
            $googleUserService = app(GoogleUserService::class);
            $googleUserService->refreshToken($googleAccount);

            return true;
        } catch (\Exception $e) {
            // Log the error for debugging
            \Illuminate\Support\Facades\Log::error('Failed to refresh Google token: '.$e->getMessage(), [
                'user_id' => $this->id,
                'google_account_id' => $googleAccount->id,
                'exception' => $e,
            ]);

            // If refresh fails, credentials are invalid
            return false;
        }
    }

    /**
     * Get the points ledger entries for the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function points()
    {
        return $this->hasMany(PointsLedger::class);
    }

    /**
     * Get the monthly points for the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function monthlyPoints()
    {
        return $this->hasMany(MonthlyPoint::class);
    }

    /**
     * Get the kudos sent by the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function kudosSent()
    {
        return $this->hasMany(Kudo::class, 'sender_id');
    }

    /**
     * Get the kudos received by the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function kudosReceived()
    {
        return $this->hasMany(Kudo::class, 'recipient_id');
    }

    /**
     * Context records created/performed by this user (actor).
     */
    public function contexts()
    {
        return $this->hasMany(Context::class);
    }

    public function getAvatarAttribute()
    {
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=random&color=fff';
    }
}
