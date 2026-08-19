<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Models\Project;
use App\Models\ProjectExpendable;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Decides which projects a portal visitor may see.
 *
 * The portal is deliberately hybrid: the person signing in with an emailed code is
 * matched against `users` by email, and may turn out to be a full team member rather
 * than an outside supplier. Access therefore comes from four independent signals, and
 * any one of them is enough:
 *
 *   1. `project_user` — they are on the project team. This is real membership, so it
 *      stands on its own and does not depend on the public share link being switched on.
 *   2. `user_interactions` with `email_sent` — someone ELSE sent them the share
 *      link from ProjectShareController. Strictly that one type; see the query.
 *   3. `otp_verifications` — they have signed in through that project's link before.
 *   4. `project_expendables` — they have sent a proposal on the project.
 *
 * Signals 2–4 are all "reached us through the public link", so they additionally
 * require `public_share_enabled`. Turning sharing off therefore removes a project from
 * an outside supplier's portal immediately, while a team member keeps seeing it.
 */
class PortalAccessService
{
    /**
     * Base query for every project this user may open in the portal.
     */
    public function accessibleProjectsQuery(User $user): Builder
    {
        return Project::query()->where(function (Builder $q) use ($user) {
            // 1. Real team membership — independent of the share toggle.
            $q->whereHas('users', fn (Builder $u) => $u->whereKey($user->getKey()));

            // 2-4. Reached the project through the public share link.
            $q->orWhere(function (Builder $shared) use ($user) {
                $shared->where('public_share_enabled', true)
                    ->where(function (Builder $via) use ($user) {
                        $via->whereIn('id', $this->invitedProjectIds($user))
                            ->orWhereIn('id', $this->proposedProjectIds($user))
                            ->orWhereIn('id', $this->verifiedProjectIds($user));
                    });
            });
        });
    }

    /**
     * @return Collection<int, Project>
     */
    public function accessibleProjects(User $user): Collection
    {
        return $this->accessibleProjectsQuery($user)
            // Everything the All-projects card needs, resolved in this one query rather
            // than lazily per card — signal 1 can legitimately return hundreds of
            // projects for a busy project manager.
            ->with(['manager:id,name', 'admin:id,name', 'client:id,name'])
            ->withCount(['milestones as active_milestone_count' => fn ($q) => $q
                ->whereNotIn('status', array_map(
                    fn (\App\Enums\MilestoneStatus $s) => $s->value,
                    PortalProjectPresenter::CLOSED_MILESTONE_STATUSES
                ))])
            ->withMax('milestones as last_milestone_date', 'completion_date')
            ->orderBy('name')
            ->get();
    }

    public function canAccess(User $user, Project $project): bool
    {
        return $this->accessibleProjectsQuery($user)->whereKey($project->getKey())->exists();
    }

    /**
     * Projects someone explicitly emailed the share link for.
     *
     * @return array<int, int>
     */
    private function invitedProjectIds(User $user): array
    {
        return UserInteraction::query()
            ->where('user_id', $user->getKey())
            ->where('interactable_type', Project::class)
            // `email_sent` only. The other interaction types (link_open, page_view,
            // proposal_submitted) are written by the visitor's own activity, so
            // accepting them would be self-granting: regenerating a leaked share
            // token kills signal 3, but self-issued rows would keep signal 2 alive
            // forever. `email_open` is writable over an unauthenticated GET by
            // EmailTrackingController — never add it here.
            ->where('interaction_type', 'email_sent')
            ->pluck('interactable_id')
            ->unique()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function proposedProjectIds(User $user): array
    {
        return ProjectExpendable::query()
            ->where('user_id', $user->getKey())
            ->pluck('project_id')
            ->unique()
            ->all();
    }

    /**
     * Projects this user has already signed into through the share link.
     *
     * `otp_verifications.project_token` holds whatever string was in the URL the code
     * was requested from — and the invite emails use the 12-character CODE, not the
     * 64-character token. Comparing it straight against `projects.public_share_token`
     * therefore never matched for anyone arriving on a pretty link, and they got a 404
     * on the project immediately after verifying their email. Match on the prefix.
     *
     * Prefix matching keeps the property that matters: regenerating a leaked share
     * token still revokes access, because the old prefix no longer matches anything.
     *
     * @return array<int, int>
     */
    private function verifiedProjectIds(User $user): array
    {
        $tokens = OtpVerification::query()
            ->where('guest_user_id', $user->getKey())
            ->whereNotNull('verified_at')
            ->pluck('project_token')
            ->filter()
            ->unique()
            // Same floor as PublicProjectController::MIN_SHARE_CODE_LENGTH: a short
            // prefix would match projects this user never had a link to.
            ->filter(fn ($token) => strlen((string) $token) >= 12)
            ->values()
            ->all();

        if (! $tokens) {
            return [];
        }

        return Project::query()
            ->where(function (Builder $q) use ($tokens) {
                foreach ($tokens as $token) {
                    if (strlen((string) $token) === 64) {
                        $q->orWhere('public_share_token', $token);
                    } else {
                        $q->orWhere('public_share_token', 'like', addcslashes((string) $token, '%_\\').'%');
                    }
                }
            })
            ->pluck('id')
            ->all();
    }
}
