<?php

namespace App\Services\Inbox;

use App\Enums\EmailAiStatus;
use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Shapes conversations into the payload the React inbox renders.
 *
 * Everything the UI needs to decide *what to show* is resolved here, on the server:
 * the reply clock, the redaction rules, and which action buttons a given viewer is
 * allowed to see. The client never re-derives permission from raw fields — it renders
 * what it is told. That keeps the two from disagreeing, and means a UI bug cannot expose
 * a body the viewer should not read (the body simply is not in the payload).
 *
 * Two redaction rules, which are NOT the same thing:
 *  - Screening: an inbound email at pending_approval_received is withheld from anyone
 *    without `approve_received_emails` until a manager releases it.
 *  - Privacy:   an email flagged is_private is withheld from anyone without
 *    `view_private_emails`, forever.
 * In both cases the body is stripped before the payload is built, not hidden with CSS.
 */
class ThreadPresenter
{
    /** Body text longer than this is truncated in list previews. */
    private const PREVIEW_CHARS = 220;

    /**
     * Per-request memo for policy checks, keyed "ability:emailId".
     *
     * Not premature: EmailPolicy::editAndApprove and EmailPolicy::delete each run several
     * uncached queries (User::hasPermission does `permissions()->where()->exists()` every
     * call, and userHasProjectPermission loads the project and role), and the presenter
     * asks about the same emails from three places per row. Without this a 25-thread page
     * ran into the thousands of queries for anyone who is not a super admin — who
     * short-circuits on Gate::before and would never have seen it in testing.
     */
    private array $abilityCache = [];

    /**
     * Same reasoning as $abilityCache, and keyed by user for the same reason —
     * InboxAccess::isManager runs uncached permission queries.
     *
     * @var array<int,bool>
     */
    private array $isManagerCache = [];

    public function __construct(
        private readonly InboxAccess $access,
        private readonly EmailBodyRenderer $bodies,
        private readonly BlockComposition $blocks,
        private readonly ReplyClock $clock,
    ) {}

    private function isManager(User $user): bool
    {
        return $this->isManagerCache[$user->id] ??= $this->access->isManager($user);
    }

    /**
     * The single authority for "may this user act on this email".
     *
     * Always EmailPolicy, never Email::$can_approve. The accessor is looser than the
     * policy in both directions (it grants on `contact_lead` where the policy does not,
     * and misses `resubmit_emails` where the policy grants), so using it to decide what
     * to render produced buttons that 403 or silently affected nothing. The endpoints all
     * authorise against the policy, so the UI must too.
     */
    private function allows(User $user, string $ability, Email $email): bool
    {
        // Keyed by user as well as email. One presenter serves one request today, but a
        // cache that silently answers for the wrong user the moment this is bound as a
        // singleton or reused in a job is not a bug worth leaving available.
        $key = $user->id.':'.$ability.':'.$email->id;

        return $this->abilityCache[$key] ??= $user->can($ability, $email);
    }

    /**
     * A row in the thread list. Carries no message bodies beyond a short preview.
     *
     * @param  Conversation  $conversation  decorated with ThreadQuery's aggregate columns
     */
    public function listItem(Conversation $conversation, User $user): array
    {
        $emails = $this->visibleEmails($conversation, $user);
        $latest = $emails->last();

        $clock = $this->replyClock($conversation, $emails);
        $counterpart = $this->counterpartName($conversation, $emails);

        $blocked = $emails->contains(fn (Email $e) => $this->isRedacted($e, $user));
        $hasPrivate = $emails->contains(fn (Email $e) => (bool) $e->is_private);
        $screening = $emails->contains(
            fn (Email $e) => $this->statusValue($e) === EmailStatus::PendingApprovalReceived->value
        );
        $pendingApproval = $emails->contains(
            fn (Email $e) => $this->statusValue($e) === EmailStatus::PendingApproval->value
        );
        $withAi = $emails->first(fn (Email $e) => $e->ai_status?->isPending());

        $preview = $blocked
            ? ($hasPrivate && ! $this->access->canSeePrivate($user)
                ? 'Contains a private message — only managers can read this thread.'
                : 'Held for screening — a manager needs to release this before the team can read it.')
            : $this->preview($latest);

        return [
            'id' => $conversation->id,
            // conversations.subject is set on every thread, so the fallback is rare — but
            // it must not trigger a template render for every row when it does fire.
            'subject' => $conversation->subject
                ?: ($latest?->subject ?: '(no subject)'),
            'preview' => $preview,
            'who' => $counterpart,
            'direction' => $latest && $this->isInbound($latest) ? 'in' : 'out',
            'project' => $this->project($conversation),
            'categories' => $this->categories($emails),

            'message_count' => (int) ($conversation->email_count ?? $emails->count()),
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'is_unread' => (int) ($conversation->unread_count ?? 0) > 0,
            'attachment_count' => $emails->sum(fn (Email $e) => $e->files_count ?? $e->files->count()),

            'last_message_at' => $this->iso($conversation->last_message_at ?? $latest?->created_at),
            'last_inbound_at' => $this->iso($conversation->last_inbound_at),
            'last_outbound_at' => $this->iso($conversation->last_outbound_at),

            'reply' => $clock,

            'flags' => [
                'has_private' => $hasPrivate,
                'is_redacted' => $blocked,
                'screening' => $screening,
                'pending_approval' => $pendingApproval,
                'with_ai' => (bool) $withAi,
                'ai_stalled' => $withAi ? $this->aiStalled($withAi) : false,
                // The EMAIL id, not the thread id. The list's "Send to AI again" button
                // acts on an email, and without this the client had nothing but the
                // conversation id to send.
                'ai_email_id' => $withAi?->id,
                // Outbound only: `status = draft` on an inbound row means "arrived, not
                // yet processed", so without the type check every thread containing a
                // client email was labelled "Not sent".
                'is_draft' => $emails->contains(
                    fn (Email $e) => ! $this->isInbound($e)
                        && $this->statusValue($e) === EmailStatus::Draft->value
                ),
            ],

            // What this viewer may do. The client renders buttons from these, and every
            // endpoint re-checks — this is presentation, not enforcement.
            // Gated on the SAME abilities the endpoints authorise against, not on a
            // looser "is a manager" idea of it — otherwise the UI offers buttons that
            // 403 on click. Delete is EmailPolicy::delete (delete_emails); privacy is
            // authorised by EmailController::togglePrivacy with that same ability.
            'can' => [
                'reply' => ! $blocked && ! $withAi,
                // Two different things, deliberately separated:
                //  - release: screened inbound mail, or an AI hold to clear. Safe in bulk
                //    from the list — it changes who may READ something already delivered.
                //  - approve: an outbound draft, which means SENDING to a client. Only
                //    offered in the thread view, where the body is on screen.
                'release' => $this->canReleaseAny($emails, $user),
                'approve' => $this->canApproveAny($emails, $user),
                'resend_to_ai' => $this->isManager($user) && (bool) $withAi,
                'delete' => $this->canDeleteAny($emails, $user),
            ],

            'last_receipt' => $this->receipt($emails->last(fn (Email $e) => ! $this->isInbound($e))),
            // Withheld from anyone the thread is redacted for. The summary is generated
            // with screened and private messages excluded (InboxAiService::threadText),
            // but a summary is a paraphrase of a thread this person cannot fully read, so
            // it stays behind the same door as the bodies.
            'ai_summary' => $blocked
                ? null
                : ($conversation->hasCurrentAiSummary($emails->count()) ? $conversation->ai_summary : null),
        ];
    }

    /** The full thread, including message bodies the viewer is allowed to read. */
    public function thread(Conversation $conversation, User $user): array
    {
        $emails = $this->visibleEmails($conversation, $user);
        $isManager = $this->isManager($user);

        $messages = $emails->map(fn (Email $email) => $this->message($email, $user))->values()->all();

        $notes = $conversation->notes
            ->map(fn ($note) => [
                'id' => $note->id,
                'kind' => 'note',
                'author' => $note->user?->name ?? 'Someone',
                'body' => $note->content,
                'created_at' => $this->iso($note->created_at),
            ])
            ->values()
            ->all();

        // Notes and messages interleave by time — the design shows them inline in the
        // thread, not in a separate pane.
        $timeline = collect(array_merge($messages, $notes))
            ->sortBy(fn ($item) => $item['created_at'] ?? '')
            ->values()
            ->all();

        $withAi = $emails->first(fn (Email $e) => $e->ai_status?->isPending());
        $held = $emails->first(fn (Email $e) => $e->ai_status === EmailAiStatus::Held);
        $awaiting = $emails->first(fn (Email $e) => in_array($this->statusValue($e), [
            EmailStatus::PendingApproval->value,
            EmailStatus::PendingApprovalReceived->value,
        ], true));

        $latestInbound = $emails->last(fn (Email $e) => $this->isInbound($e));
        $blocked = $emails->contains(fn (Email $e) => $this->isRedacted($e, $user));

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject
                ?: ($emails->last() ? $this->bodies->subject($emails->last()) : '(no subject)'),
            'who' => $this->counterpartName($conversation, $emails),
            'project' => $this->project($conversation),
            'categories' => $this->categories($emails),
            'message_count' => $emails->count(),
            // POST /api/projects/{id}/email-preview validates client_id against the
            // clients table, so a lead thread cannot preview a template. Null here tells
            // the composer to hide the preview rather than fire a request that 422s.
            'preview_client_id' => $conversation->conversable instanceof \App\Models\Client
                ? $conversation->conversable->id
                : null,
            'reply' => $this->replyClock($conversation, $emails),
            'timeline' => $timeline,

            'approval' => $awaiting ? [
                'email_id' => $awaiting->id,
                'kind' => $this->statusValue($awaiting) === EmailStatus::PendingApprovalReceived->value
                    ? 'screening'
                    : 'draft',
                'author' => $awaiting->sender?->name ?? 'the team',
                'since' => $this->iso($awaiting->created_at),
                // EmailPolicy::editAndApprove, because that is what the send endpoint
                // authorises. can_approve is a looser accessor and would show an Approve
                // button that 403s — notably on lead threads.
                'can_act' => $this->allows($user, 'editAndApprove', $awaiting),
                'ai_reason' => $held?->ai_reason,
                // The draft as it stands, so "Edit & approve" can seed the editor and
                // send THIS email rather than creating a second one.
                'subject' => $this->isRedacted($awaiting, $user) ? null : $this->bodies->subject($awaiting),
                'body' => $this->isRedacted($awaiting, $user) ? null : $this->bodies->body($awaiting),
                // Templated drafts CAN now be approved here: the body shown above is the
                // rendered template (EmailBodyRenderer), and editAndApprove re-renders the
                // same thing from the same template_data on send. The flag stays so the UI
                // can say the text is template-generated and hide the free-text editor —
                // "Edit & approve" would otherwise offer a textarea whose contents are
                // discarded at send time. Editing the template FIELDS is still a classic-
                // inbox job.
                'is_template' => (bool) $awaiting->template_id,
                // Same reasoning as is_template, for the block builder. The body shown
                // above is the PREVIEW render — `cid:` references swapped for signed GCS
                // URLs so a browser can display them — and editAndApprove re-renders from
                // the stored blocks rather than accepting whatever is posted back. So a
                // free-text editor here would silently discard every edit. The approver
                // can still approve it or send it back; changing the content means
                // reopening the builder.
                'is_blocks' => $this->blocks->isBlockEmail($awaiting),
            ] : null,

            'ai' => [
                'enabled' => (bool) config('inbox.ai.enabled'),
                // See listItem(): withheld when any message on the thread is redacted
                // for this viewer.
                'summary' => ! $blocked && $conversation->hasCurrentAiSummary($emails->count())
                    ? $conversation->ai_summary
                    : null,
                'summary_at' => $this->iso($conversation->ai_summary_at),
                'generated_from' => $conversation->ai_summary_email_count,
                'task_suggestion' => $conversation->ai_task_suggestion,
                'checking' => $withAi ? [
                    'email_id' => $withAi->id,
                    'author' => $withAi->sender?->name ?? 'the team',
                    'since' => $this->iso($withAi->created_at),
                    'stalled' => $this->aiStalled($withAi),
                ] : null,
                'draft' => $latestInbound && ! $blocked ? $latestInbound->ai_draft : null,
            ],

            'can' => [
                'reply' => ! $blocked && ! $withAi,
                'note' => true,
                'approve' => $this->canApproveAny($emails, $user),
                'resend_to_ai' => $isManager && (bool) $withAi,
                'delete' => $this->canDeleteAny($emails, $user),
                'toggle_privacy' => $this->canDeleteAny($emails, $user),
                'create_task' => ! $blocked,
            ],

            // Why the reply box is locked, in the words the design uses. Null when it is
            // not locked — the client does not compose this sentence itself.
            'reply_lock' => $this->replyLock($emails, $user, $blocked, (bool) $withAi),
        ];
    }

    private function message(Email $email, User $user): array
    {
        $redacted = $this->isRedacted($email, $user);
        $privateHidden = $email->is_private && ! $this->access->canSeePrivate($user);

        return [
            'id' => $email->id,
            'kind' => 'message',
            'direction' => $this->isInbound($email) ? 'in' : 'out',
            'author' => $this->authorName($email),
            'to' => $this->recipients($email),
            'subject' => $redacted ? null : $this->bodies->subject($email),
            'status' => $this->statusValue($email),
            'status_label' => $this->statusLabel($email),
            'created_at' => $this->iso($email->sent_at ?? $email->created_at),
            'is_private' => (bool) $email->is_private,
            'is_read' => $email->isReadBy($user->id),

            // Bodies are omitted entirely when withheld, not blanked client-side.
            'redacted' => $redacted,
            'redaction' => $privateHidden ? 'private' : ($redacted ? 'screening' : null),
            // Rendered, not raw. A templated email stores body = null and keeps its text
            // in the template plus template_data — reading the column directly showed
            // every templated email as blank.
            'body_html' => $redacted ? null : $this->bodies->body($email),
            'is_templated' => $this->bodies->isTemplated($email),
            // Distinguishes "nothing to show" from "we could not build it" — the client
            // shows a link to the classic page for the latter rather than an empty card.
            'render_failed' => ! $redacted
                && $this->bodies->isTemplated($email)
                && $this->bodies->body($email) === null,
            'snippet' => $redacted
                ? ($privateHidden ? 'Private message — managers only' : 'Held for screening')
                : $this->preview($email, 120),

            'summary' => $redacted ? null : $email->ai_summary,
            // Column names are FileAttachment's, not guesses: `filename`, `file_size`,
            // and the appended `path_url` accessor.
            'files' => $redacted ? [] : $email->files->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->filename,
                'size' => $f->file_size,
                'mime_type' => $f->mime_type,
                'url' => $f->path_url,
            ])->values()->all(),

            'receipt' => $this->receipt($email),
            'rejection_reason' => $email->rejection_reason,
            'can' => [
                // can_send mirrors what /api/emails/{id}/edit-and-approve will allow, so
                // the button and the endpoint cannot disagree.
                'approve' => $this->allows($user, 'editAndApprove', $email),
                // togglePrivacy authorises EmailPolicy::delete — match it exactly.
                'toggle_privacy' => $this->allows($user, 'delete', $email),
            ],
        ];
    }

    /**
     * The reply clock for one thread.
     *
     * `minutes_left` is negative once breached — the client formats "Overdue by 12m" from
     * the sign rather than from a separate flag, so the two can never disagree.
     */
    private function replyClock(Conversation $conversation, Collection $emails): array
    {
        $sla = (int) config('inbox.sla_minutes', 60);

        $inbound = $conversation->last_inbound_at
            ?? $emails->last(fn (Email $e) => $this->isInbound($e))?->created_at;
        // isDelivered(), not a bare comparison against `sent` — the legacy `approved`
        // status counts too, and ReplyClock is the one place that decides. Hard-coding it
        // here is how the list ends up saying "overdue" on a thread this view calls
        // answered.
        $outbound = $conversation->last_outbound_at
            ?? $emails->last(fn (Email $e) => $this->clock->isDelivered($e))?->created_at;

        $inbound = $inbound ? Carbon::parse($inbound) : null;
        $outbound = $outbound ? Carbon::parse($outbound) : null;

        $needsReply = $inbound !== null && ($outbound === null || $outbound->lt($inbound));

        /*
         * Out of the cutover's scope: the clock has no opinion, and must not present one.
         *
         * ThreadQuery already excludes these from the "Needs reply" list. Without the same
         * check here, opening one of those threads from Received or All mail would still
         * show a red "overdue by 14 months" pill — the list and the thread disagreeing
         * about the same email, which is exactly the confusion the cutover exists to
         * remove. `out_of_scope` is returned so the UI can explain the silence rather than
         * just showing nothing. See ReplyClock.
         */
        $inScope = $this->clock->inScope($inbound);

        if (! $needsReply || ! $inScope) {
            return [
                'needs_reply' => false,
                'sla_minutes' => $sla,
                'due_at' => null,
                'minutes_left' => null,
                'answered_at' => $this->iso($outbound),
                // True only when the clock declined to judge, so "answered" and "before
                // we started measuring" stay distinguishable in the UI.
                'out_of_scope' => $needsReply && ! $inScope,
            ];
        }

        $dueAt = $inbound->copy()->addMinutes($sla);

        return [
            'needs_reply' => true,
            'sla_minutes' => $sla,
            'due_at' => $this->iso($dueAt),
            'minutes_left' => (int) round(Carbon::now()->diffInSeconds($dueAt, false) / 60),
            'waiting_since' => $this->iso($inbound),
            'answered_at' => null,
            'out_of_scope' => false,
        ];
    }

    private function replyLock(Collection $emails, User $user, bool $blocked, bool $withAi): ?string
    {
        if ($withAi) {
            return 'Locked while the AI checker verifies it. If it takes too long, send it to the checker again from the banner above.';
        }

        if ($emails->contains(fn (Email $e) => $e->is_private) && ! $this->access->canSeePrivate($user)) {
            return 'This thread holds a private message, so replies are handled by a manager.';
        }

        if ($blocked) {
            return "No replying until a manager releases this message — you'll get a notification when they do.";
        }

        return null;
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Emails on the thread, oldest first.
     *
     * Nothing is dropped here — a withheld message still occupies a slot in the thread so
     * the viewer can see that something exists and who it is from. What gets withheld is
     * the body, in message()/listItem(). Dropping the row entirely would let a manager
     * and a contractor disagree about how many messages a thread has.
     */
    private function visibleEmails(Conversation $conversation, User $user): Collection
    {
        return $conversation->emails
            ->sortBy(fn (Email $e) => ($e->sent_at ?? $e->created_at)?->timestamp ?? 0)
            ->values();
    }

    private function isInbound(Email $email): bool
    {
        $type = $email->type instanceof EmailType ? $email->type->value : (string) $email->type;

        return $type === EmailType::Received->value;
    }

    private function statusValue(Email $email): string
    {
        return $email->status instanceof EmailStatus
            ? $email->status->value
            : strtolower((string) $email->status);
    }

    /**
     * Should this email's body be withheld from this viewer?
     *
     * Private beats screening: a private message stays hidden even from someone who could
     * release a screened one, unless they also hold view_private_emails.
     */
    private function isRedacted(Email $email, User $user): bool
    {
        if ($email->is_private && ! $this->access->canSeePrivate($user)) {
            return true;
        }

        return $this->statusValue($email) === EmailStatus::PendingApprovalReceived->value
            && ! $user->hasPermission(Email::APPROVE_RECEIVED_EMAILS_PERMISSION);
    }

    private function aiStalled(Email $email): bool
    {
        $minutes = (int) config('inbox.ai.stall_minutes', 5);
        $since = $email->ai_checked_at ?? $email->updated_at ?? $email->created_at;

        return $since ? Carbon::parse($since)->lt(Carbon::now()->subMinutes($minutes)) : false;
    }

    private function canApproveAny(Collection $emails, User $user): bool
    {
        return $emails->contains(fn (Email $e) => $this->allows($user, 'editAndApprove', $e));
    }

    /**
     * Can this user release something on the thread — screened inbound mail, or an email
     * the AI checker is holding? These are what the bulk endpoint's `approve` action
     * actually performs; an outbound draft is not one of them.
     */
    private function canReleaseAny(Collection $emails, User $user): bool
    {
        return $emails->contains(
            fn (Email $e) => $this->allows($user, 'editAndApprove', $e) && $this->isReleasable($e)
        );
    }

    /**
     * The states the bulk `approve` action can actually change.
     *
     * Must stay in lockstep with InboxThreadController::approveThread — anything counted
     * here that the endpoint skips becomes a button that reports "0 affected".
     */
    public function isReleasable(Email $email): bool
    {
        return $this->statusValue($email) === EmailStatus::PendingApprovalReceived->value
            || $email->ai_status === EmailAiStatus::Held;
    }

    /**
     * EmailPolicy::delete, i.e. the `delete_emails` permission — the same ability the
     * delete endpoint authorises. Being an approver is not the same as being allowed to
     * delete, and the two were previously conflated here.
     */
    private function canDeleteAny(Collection $emails, User $user): bool
    {
        return $emails->isNotEmpty()
            && $emails->contains(fn (Email $e) => $this->allows($user, 'delete', $e));
    }

    private function statusLabel(Email $email): string
    {
        return match ($this->statusValue($email)) {
            EmailStatus::Sent->value => 'Sent',
            EmailStatus::Received->value => 'Received',
            EmailStatus::Draft->value => 'Draft',
            EmailStatus::PendingApproval->value => 'Needs approval',
            EmailStatus::PendingApprovalReceived->value => 'Screening',
            EmailStatus::Rejected->value, EmailStatus::RejectedReceived->value => 'Sent back',
            EmailStatus::Delayed->value => 'Scheduled',
            default => ucfirst(str_replace('_', ' ', $this->statusValue($email))),
        };
    }

    private function authorName(Email $email): string
    {
        if ($email->sender?->name) {
            return $email->sender->name;
        }

        $conversable = $email->conversation?->conversable;

        return $this->isInbound($email)
            ? ($conversable?->name ?? 'Client')
            : 'OZee team';
    }

    /** Who the thread is *with*, from the team's point of view. */
    private function counterpartName(Conversation $conversation, Collection $emails): string
    {
        if ($conversation->conversable?->name) {
            return $conversation->conversable->name;
        }

        $inbound = $emails->last(fn (Email $e) => $this->isInbound($e));

        return $inbound ? $this->authorName($inbound) : 'Unknown sender';
    }

    private function recipients(Email $email): string
    {
        $to = $email->to;

        if (is_array($to)) {
            $to = implode(', ', array_filter($to));
        }

        return $to ? 'to '.$to : '';
    }

    private function project(Conversation $conversation): array
    {
        if (! $conversation->project) {
            return ['id' => null, 'name' => 'Lead (no project)', 'short' => 'Lead'];
        }

        $name = $conversation->project->name;
        // Project names read "Client — what we're doing"; the list only has room for the
        // client half, so split on the em dash the naming convention uses.
        $short = trim(explode('—', $name)[0]) ?: $name;

        return ['id' => $conversation->project->id, 'name' => $name, 'short' => $short];
    }

    private function categories(Collection $emails): array
    {
        return $emails
            ->flatMap(fn (Email $e) => $e->categories)
            ->unique('id')
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->values()
            ->all();
    }

    private function receipt(?Email $email): ?array
    {
        if (! $email || $this->isInbound($email)) {
            return null;
        }

        if ($this->statusValue($email) !== EmailStatus::Sent->value) {
            return null;
        }

        return [
            'opened' => (bool) $email->read_at,
            'at' => $this->iso($email->read_at),
        ];
    }

    private function preview(?Email $email, int $chars = self::PREVIEW_CHARS): string
    {
        if (! $email) {
            return '';
        }

        /*
         * A templated email is described, not rendered, in the list.
         *
         * Rendering one costs at least two queries (renderEmailContent does its own
         * EmailTemplate::findOrFail regardless of eager loading) plus a lookup per
         * source-model placeholder. Doing that for the newest message on each of 25 rows
         * is ~50+ queries to produce a 220-character preview nobody reads closely. The
         * template's name comes free off the `emails.template` relation the controller
         * already eager-loads, and the full rendered text is one click away in the thread.
         */
        if ($this->bodies->isTemplated($email)) {
            $name = $email->relationLoaded('template') ? $email->template?->name : null;

            return $name ? "Template: {$name}" : 'Template email — open the thread to read it';
        }

        $html = $this->bodies->body($email);

        if ($html === null || trim(strip_tags($html)) === '') {
            return '';
        }

        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return mb_strlen($text) > $chars ? mb_substr($text, 0, $chars).'…' : $text;
    }

    private function iso($value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon ? $value->toIso8601String() : Carbon::parse($value)->toIso8601String();
    }
}
