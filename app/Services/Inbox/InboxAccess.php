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
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_emails')) {
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
        return $user->isSuperAdmin() || $user->hasPermission('contact_lead') || $user->hasPermission('contact_leads');
    }

    public function canSeePrivate(User $user): bool
    {
        return $user->isSuperAdmin() || (bool) $user->hasPermission('view_private_emails');
    }

    /**
     * Managers, in the sense the redesigned inbox uses the word: people who can release
     * screened inbound mail and approve outbound drafts. The mock's "Viewing as
     * manager / contractor" switch is this, resolved from real permissions.
     */
    public function isManager(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isManager()
            || $user->hasPermission('approve_all_emails')
            || $user->hasPermission('approve_emails')
            || $user->hasPermission(\App\Models\Email::APPROVE_RECEIVED_EMAILS_PERMISSION);
    }

    /**
     * May this user compose a TEMPLATE email?
     *
     * `compose_emails` is a real, seeded permission held by Manager and two other roles —
     * the same slug the legacy inbox gates its "Compose Email" button on.
     */
    public function canComposeTemplate(User $user): bool
    {
        return $user->isSuperAdmin() || (bool) $user->hasPermission('compose_emails');
    }

    /**
     * May this user compose a FREE-FORM (custom) email?
     *
     * The legacy inbox gates its "Custom Email" button on `create_custom_emails` — a slug
     * that appears in two Vue files and nowhere else: not in the seeder, not in any
     * migration, not in any policy. Because it can never be granted, the only people who
     * see that button are super admins, who bypass every permission check via
     * `Gate::before`. So the effective legacy rule is "super admins only", by accident.
     *
     * Reproduced deliberately here rather than inherited by accident: the same slug is
     * still honoured, so seeding it later starts working with no code change, and until
     * then the behaviour matches the page this one replaces.
     *
     * Unlike the legacy page, this IS enforced server-side — see InboxReplyController.
     * The old `POST /api/emails` only ever hid the button.
     */
    public function canComposeCustom(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('create_custom_emails')
            || ($user->role?->slug === 'super-admin');
    }

    /**
     * May this user build a PROJECT UPDATE (the block builder)?
     *
     * Anyone who may compose at all. The builder used to sit behind
     * `canComposeCustom`, on the reasoning that its content is free-form and offering it
     * on the template permission would hand every non-admin the free-form composer
     * through a different door. In practice that made it invisible to everyone but super
     * admins, and it is not the same door: the builder emits a fixed set of typed blocks
     * — text, bullets, a link, an image — rendered server-side by BlockRenderer into our
     * own markup. There is no HTML passthrough and no free-text recipient, so what a
     * non-admin can put in front of a client through it is the same class of thing a
     * template already puts there. Deliberately widened; see canComposeCustom, which is
     * unchanged and still governs the genuinely free-form body.
     */
    public function canComposeBlocks(User $user): bool
    {
        return $this->canComposeTemplate($user) || $this->canComposeCustom($user);
    }

    /**
     * May this user mark a message PRIVATE?
     *
     * `delete_emails`, which is what `EmailPolicy::delete` checks and therefore what the
     * existing after-the-fact toggle already uses (Api\EmailController::togglePrivacy,
     * surfaced as `can.toggle_privacy`). Deliberately the same population: a compose-time
     * switch that a different set of people could see than the one next to the sent
     * message would be two controls disagreeing about who may keep a message from the
     * team.
     *
     * Note this is NOT `view_private_emails`. That governs READING private mail, and the
     * two are separate on purpose — but it means someone can mark a reply private and
     * then not be able to open it, or reply on that thread again. The composers say so.
     */
    public function canMarkPrivate(User $user): bool
    {
        return $user->isSuperAdmin() || (bool) $user->hasPermission('delete_emails');
    }

    /**
     * May this user type an email address by hand?
     *
     * Almost nobody, by design. Client mail travels one route — from our authorised
     * mailbox, signed with our details, to the clients attached to the project — and the
     * whole reason the integration exists is that a client can answer us without being
     * able to reach an individual staff member directly. A free-text recipient box is a
     * hole straight through that, so the composer resolves recipients from the project and
     * the server ignores anything posted.
     *
     * The one exception is forwarding a thread somewhere new, which is a deliberate,
     * separate act. That is what this gates. Seeded to super-admin only; see
     * 2026_08_20_120000_add_email_custom_recipients_permission.
     */
    public function canAddressManually(User $user): bool
    {
        return $user->isSuperAdmin() || (bool) $user->hasPermission('email_custom_recipients');
    }

    /**
     * Projects this user may actually START an email on.
     *
     * Mirrors the permissions from the legacy /inbox:
     * - Super Admins and users with `view_all_projects` or `view_all_emails` can compose on any project.
     * - Otherwise: projects the user is explicitly assigned to (member, admin, or manager).
     *
     * @return array<int>
     */
    public function composableProjectIds(User $user): array
    {
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_projects') || $user->hasPermission('view_all_emails')) {
            return Project::query()->pluck('id')->all();
        }

        return Project::query()
            ->where(function ($q) use ($user) {
                $q->whereHas('users', fn ($u) => $u->where('users.id', $user->id))
                    ->orWhere('project_admin_id', $user->id)
                    ->orWhere('project_manager_id', $user->id);
            })
            ->pluck('id')
            ->all();
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
