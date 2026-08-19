<?php

namespace App\Services\Inbox;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Who can see which threads.
 *
 * Lifted from Api\InboxController::getAccessibleProjectIds() so the redesigned inbox
 * enforces exactly the same rule as the page it will eventually replace. Deliberately a
 * copy rather than a refactor of that controller: the legacy /inbox page stays live and
 * untouched, so the two cannot drift into disagreeing about who sees what only if this
 * file is kept in step — which is why the rule is documented here rather than inferred.
 *
 * The rule, in words:
 *  - `view_all_emails` sees every project.
 *  - Otherwise: projects the user is a member of, where their project role carries
 *    `view_emails`.
 *  - `contact_lead` additionally sees conversations with no project (leads).
 */
class InboxAccess
{
    /** @return array<int> */
    public function projectIds(User $user): array
    {
        if ($user->hasPermission('view_all_emails')) {
            return Project::query()->pluck('id')->all();
        }

        return Project::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->whereExists(function ($query) use ($user) {
                $query->select(DB::raw(1))
                    ->from('project_user')
                    ->join('role_permission', 'project_user.role_id', '=', 'role_permission.role_id')
                    ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
                    ->whereColumn('project_user.project_id', 'projects.id')
                    ->where('project_user.user_id', $user->id)
                    ->where('permissions.slug', 'view_emails');
            })
            ->pluck('id')
            ->all();
    }

    public function canSeeLeads(User $user): bool
    {
        return $user->hasPermission('contact_lead') || $user->hasPermission('contact_leads');
    }

    public function canSeePrivate(User $user): bool
    {
        return (bool) $user->hasPermission('view_private_emails');
    }

    /**
     * Managers, in the sense the redesigned inbox uses the word: people who can release
     * screened inbound mail and approve outbound drafts. The mock's "Viewing as
     * manager / contractor" switch is this, resolved from real permissions.
     */
    public function isManager(User $user): bool
    {
        return $user->hasPermission('approve_all_emails')
            || $user->hasPermission('approve_emails')
            || $user->hasPermission(\App\Models\Email::APPROVE_RECEIVED_EMAILS_PERMISSION);
    }

    /**
     * True when the user can see nothing at all, so callers can return an empty page
     * without running the (expensive) thread query.
     */
    public function seesNothing(User $user): bool
    {
        return empty($this->projectIds($user)) && ! $this->canSeeLeads($user);
    }
}
