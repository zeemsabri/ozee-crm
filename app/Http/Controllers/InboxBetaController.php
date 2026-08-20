<?php

namespace App\Http\Controllers;

use App\Services\Inbox\InboxAccess;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Serves the redesigned inbox at /inbox/beta.
 *
 * Renders 'React/Inbox/Index', and that "React/" prefix is load-bearing: it is what
 * resources/views/app.blade.php and resources/js/app.js both key off to boot React rather
 * than Vue for this request. See the frontend_react_vue_coexistence note.
 *
 * The page is a shell — it ships only what the first paint needs and fetches threads from
 * Api\InboxThreadController. That keeps this route cheap and means the legacy /inbox page
 * (which is also a pure client-fetch shell) and this one behave the same under load.
 */
class InboxBetaController extends Controller
{
    public function __construct(private readonly InboxAccess $access) {}

    public function __invoke(): Response
    {
        $user = request()->user();

        return Inertia::render('React/Inbox/Index', [
            'settings' => [
                'sla_minutes' => (int) config('inbox.sla_minutes', 60),
                'per_page' => (int) config('inbox.per_page', 25),
                'is_manager' => $this->access->isManager($user),
                'can_see_private' => $this->access->canSeePrivate($user),
                // Which composer modes this person gets. Matches the legacy inbox's two
                // buttons; see InboxAccess for why custom is effectively super-admin only.
                'can_compose_template' => $this->access->canComposeTemplate($user),
                'can_compose_custom' => $this->access->canComposeCustom($user),
                // Whether the composer draws a free-text address field at all. Client mail
                // otherwise only ever goes to the project's clients — see
                // InboxAccess::canAddressManually.
                'can_address_manually' => $this->access->canAddressManually($user),
                'ai' => [
                    'enabled' => (bool) config('inbox.ai.enabled'),
                    'summarise' => (bool) config('inbox.ai.summarise'),
                    'drafts' => (bool) config('inbox.ai.draft_replies'),
                    'check_outbound' => (bool) config('inbox.ai.check_outbound'),
                ],
                // Who the block builder's preview signs off as. Cosmetic — the real
                // signature is added by the mail layout at send time — but showing the
                // signed-in user's own name is the difference between a preview someone
                // trusts and one they check twice.
                'sign_off' => [
                    'name' => $user?->name,
                    'role' => $user?->role?->name,
                ],
                // Plain href, not an Inertia <Link>: crossing back to the Vue page needs a
                // full reload so app.js re-runs and boots Vue instead of React.
                'classic_url' => route('inbox'),
            ],
            // Deep link from a notification: /inbox/beta?thread=123 opens that thread.
            'initialThreadId' => request()->integer('thread') ?: null,
        ]);
    }
}
