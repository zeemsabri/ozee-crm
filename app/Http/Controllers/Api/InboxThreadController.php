<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmailAiStatus;
use App\Enums\EmailDraftStatus;
use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Http\Controllers\Controller;
use App\Jobs\Inbox\CheckEmailWithAi;
use App\Jobs\Inbox\DraftReplyForEmail;
use App\Jobs\Inbox\SummariseConversation;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Project;
use App\Models\UserInteraction;
use App\Services\Inbox\InboxAccess;
use App\Services\Inbox\ThreadPresenter;
use App\Services\Inbox\ThreadQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * JSON API for the redesigned inbox at /inbox/beta.
 *
 * Every route here is NEW. Nothing in Api\InboxController or Api\EmailController is
 * touched, renamed or re-shaped — the legacy Vue /inbox page, PendingApprovals.vue,
 * Rejected.vue and Composer.vue all keep the exact endpoints they have today. The two
 * inboxes read the same tables and can be used side by side by the same person.
 *
 * The one shared write is the read marker (`user_interactions`), which is deliberate:
 * marking a thread read here should mark it read there.
 */
class InboxThreadController extends Controller
{
    public function __construct(
        private readonly ThreadQuery $threads,
        private readonly ThreadPresenter $presenter,
        private readonly InboxAccess $access,
    ) {}

    /** GET /api/inbox/threads — the list. */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $filters = $this->filters($request);

        if ($this->access->seesNothing($user)) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 0],
                'counts' => array_fill_keys(ThreadQuery::VIEWS, 0),
                'overdue_count' => 0,
            ]);
        }

        $page = $this->threads->paginate($user, $filters);

        // emails.conversation.project is eager-loaded because EmailPolicy and the
        // can_approve accessor both dereference it per email; without it every
        // authorisation check on the page fires its own pair of queries.
        $page->getCollection()->load([
            'project:id,name',
            'conversable',
            'notes.user:id,name',
            'emails' => fn ($q) => $q->orderBy('created_at'),
            'emails.sender',
            'emails.conversation.project:id,name',
            // Templated emails render their body from the template on read
            // (EmailBodyRenderer); without this that is a query per email.
            'emails.template.placeholders',
            'emails.categories:id,name',
            'emails.files',
            // Deliberately NOT emails.contexts here: the list shows a one-line preview,
            // never the context, so loading it would be two extra queries per page for
            // something nobody sees. The thread detail loads it.
        ]);

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn (Conversation $c) => $this->presenter->listItem($c, $user))
                ->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
            // Counts ignore the *view* but respect the other filters, so the sidebar
            // badges describe what clicking each view would actually show.
            'counts' => $this->threads->counts($user, $filters),
            'overdue_count' => $this->threads->overdueCount($user, $filters),
        ]);
    }

    /** GET /api/inbox/threads/{conversation} — one thread, with bodies. */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeThread($conversation, $user);

        $conversation->load([
            'project:id,name',
            'conversable',
            'notes.user:id,name',
            'emails' => fn ($q) => $q->orderBy('created_at'),
            'emails.sender',
            'emails.conversation.project:id,name',
            // Templated emails render their body from the template on read
            // (EmailBodyRenderer); without this that is a query per email.
            'emails.template.placeholders',
            'emails.approver:id,name',
            'emails.categories:id,name',
            'emails.files',
            // The AI context the automation writes per email (Context model). Eager
            // loaded because the timeline reads it for every message, and its author is
            // shown alongside it.
            'emails.contexts' => fn ($q) => $q->latest('id'),
            'emails.contexts.user:id,name',
        ]);

        // Opening a thread marks it read — the same UserInteraction row the legacy page
        // writes, so read state is shared between the two inboxes.
        $this->markRead($conversation, $user);

        /*
         * No summary is generated here any more.
         *
         * This used to dispatch SummariseConversation whenever the thread had no current
         * summary, so merely OPENING a thread called a model. That was already
         * questionable; once the thread started polling itself every minute it became
         * indefensible, because each poll re-enters this method and the summary is not
         * current until the job lands — so a thread left open on a second monitor billed a
         * summary a minute, forever, for something nobody asked for.
         *
         * Summarising is now an explicit action: POST inbox/threads/{id}/summarise. See
         * summarise() below.
         */

        // Same relation list as above, including emails.conversation.project — refresh()
        // drops nested eager loads, so omitting it here would silently reintroduce a lazy
        // load per email inside every policy check.
        $conversation->refresh()->load([
            'project:id,name',
            'conversable',
            'notes.user:id,name',
            'emails' => fn ($q) => $q->orderBy('created_at'),
            'emails.sender',
            'emails.conversation.project:id,name',
            // Templated emails render their body from the template on read
            // (EmailBodyRenderer); without this that is a query per email.
            'emails.template.placeholders',
            'emails.approver:id,name',
            'emails.categories:id,name',
            'emails.files',
            // The AI context the automation writes per email (Context model). Eager
            // loaded because the timeline reads it for every message, and its author is
            // shown alongside it.
            'emails.contexts' => fn ($q) => $q->latest('id'),
            'emails.contexts.user:id,name',
        ]);

        return response()->json(['data' => $this->presenter->thread($conversation, $user)]);
    }

    /** GET /api/inbox/filters — projects and categories for the filter rail. */
    public function filterOptions(): JsonResponse
    {
        $user = Auth::user();
        $projectIds = $this->access->projectIds($user);

        $projects = Project::query()
            ->whereIn('id', $projectIds ?: [0])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->name])
            ->values()
            ->all();

        if ($this->access->canSeeLeads($user)) {
            $projects[] = ['value' => 'leads', 'label' => 'Leads (no project)'];
        }

        // Projects the composer may target — a strict subset of the filter list above,
        // because starting an email requires project membership while merely reading its
        // mail does not. See InboxAccess::composableProjectIds.
        $composableIds = $this->access->composableProjectIds($user);
        $composable = Project::query()
            ->whereIn('id', $composableIds ?: [0])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])
            ->values();

        return response()->json([
            'projects' => array_merge([['value' => 'all', 'label' => 'All my projects']], $projects),
            'compose_projects' => $composable,
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
                ->values(),
            'sla_minutes' => (int) config('inbox.sla_minutes', 60),
            'is_manager' => $this->access->isManager($user),
            'can_compose_template' => $this->access->canComposeTemplate($user),
            'can_compose_custom' => $this->access->canComposeCustom($user),
            'ai' => [
                'enabled' => (bool) config('inbox.ai.enabled'),
                'check_outbound' => (bool) config('inbox.ai.check_outbound'),
            ],
        ]);
    }

    /** POST /api/inbox/threads/{conversation}/read — mark every message read / unread. */
    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeThread($conversation, $user);

        $request->boolean('unread')
            ? $this->markUnread($conversation, $user)
            : $this->markRead($conversation->load('emails'), $user);

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/inbox/threads/{conversation}/notes — add an internal team note.
     *
     * Stored as a Comment on the conversation. Never sent to the client, and nothing in
     * the send path reads the comments table.
     */
    public function addNote(Request $request, Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeThread($conversation, $user);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $note = $conversation->notes()->create([
            'content' => $data['body'],
            'user_id' => $user->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $note->id,
                'kind' => 'note',
                'author' => $user->name,
                'body' => $note->content,
                'created_at' => $note->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * POST /api/inbox/bulk — act on several threads at once.
     *
     * Approve is per-EMAIL authorised even in bulk: the action loops the selected threads'
     * pending emails and skips any the caller cannot approve, rather than authorising the
     * thread once. The response reports how many were actually acted on, so a partially
     * permitted selection cannot look like a full success.
     */
    public function bulk(Request $request): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'action' => ['required', Rule::in(['read', 'unread', 'approve', 'categorise', 'delete'])],
            'conversation_ids' => ['required', 'array', 'min:1', 'max:200'],
            'conversation_ids.*' => ['integer'],
            'category_ids' => ['array', 'required_if:action,categorise'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $conversations = Conversation::with('emails')
            ->whereIn('id', $data['conversation_ids'])
            ->get()
            ->filter(fn (Conversation $c) => $this->canSeeThread($c, $user));

        $affected = 0;

        foreach ($conversations as $conversation) {
            switch ($data['action']) {
                case 'read':
                    $this->markRead($conversation, $user);
                    $affected++;
                    break;

                case 'unread':
                    $this->markUnread($conversation, $user);
                    $affected++;
                    break;

                case 'approve':
                    $affected += $this->approveThread($conversation, $user);
                    break;

                case 'categorise':
                    $categoryIds = $data['category_ids'] ?? [];

                    if (empty($categoryIds) || $conversation->emails->isEmpty()) {
                        break; // nothing applied, so nothing to count
                    }

                    foreach ($conversation->emails as $email) {
                        $email->attachCategories($categoryIds);
                    }
                    $affected++;
                    break;

                case 'delete':
                    // Soft delete only, and only the local copy. The Gmail copy is
                    // untouched — matching the legacy page's DELETE /api/emails/{id}
                    // default, and what the design's toast says.
                    //
                    // Authorised per email against EmailPolicy::delete (`delete_emails`),
                    // the same ability the single-email endpoint uses. Gating this on
                    // "is a manager" would have let an approver bulk-delete what the
                    // policy forbids them deleting one at a time.
                    $deletable = $conversation->emails->filter(fn (Email $e) => $user->can('delete', $e));

                    if ($deletable->isNotEmpty()) {
                        Email::whereIn('id', $deletable->pluck('id'))->delete();
                        $affected++;
                    }
                    break;
            }
        }

        return response()->json([
            'success' => true,
            'affected' => $affected,
            'requested' => count($data['conversation_ids']),
        ]);
    }

    /**
     * POST /api/inbox/emails/{email}/resend-to-ai — put a stuck draft back in the queue.
     *
     * The design offers this when a check has been running longer than it should. Only
     * meaningful while the outbound checker is switched on.
     */
    public function resendToAi(Email $email): JsonResponse
    {
        $user = Auth::user();

        // Visibility first. Being a manager on ONE project is not permission to touch an
        // email on another — without this, any approver could resubmit an arbitrary email
        // id and push its subject and body to Gemini from a thread they cannot open.
        $conversation = $email->conversation;
        abort_unless(
            $conversation && $this->canSeeThread($conversation->load('emails'), $user),
            403,
            'You cannot act on that email.'
        );

        if ($email->is_private && ! $this->access->canSeePrivate($user)) {
            abort(403, 'You cannot act on that email.');
        }

        if (! $this->access->isManager($user)) {
            abort(403, 'Only a manager can resubmit a draft to the checker.');
        }

        // Only something the checker actually has. Re-queueing a sent or approved email
        // would silently re-run a check whose verdict can no longer change anything.
        if (! ($email->ai_status?->isPending() || $email->ai_status?->needsHuman())) {
            return response()->json([
                'success' => false,
                'message' => 'That email is not with the checker.',
            ], 422);
        }

        if (! config('inbox.ai.enabled') || ! config('inbox.ai.check_outbound')) {
            return response()->json([
                'success' => false,
                'message' => 'The AI checker is switched off.',
            ], 422);
        }

        $email->forceFill([
            'ai_status' => EmailAiStatus::Queued,
            'ai_reason' => null,
            'ai_checked_at' => now(),
        ])->save();

        CheckEmailWithAi::dispatch($email->id);

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/inbox/emails/{email}/draft — ask for (or refresh) an AI reply draft.
     *
     * Synchronous-looking to the caller but queued: the client polls the thread rather
     * than holding a request open for a model round trip.
     */
    public function requestDraft(Email $email): JsonResponse
    {
        $user = Auth::user();
        $conversation = $email->conversation;

        if (! $conversation || ! $this->canSeeThread($conversation->load('emails'), $user)) {
            abort(403);
        }

        /*
         * Name the switch that is off, rather than saying "AI drafts are switched off".
         *
         * These are two separate env vars and the failure looks identical from the UI, so
         * a single message sent people to check the wrong one.
         */
        if (! config('inbox.ai.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'AI is switched off for the inbox (INBOX_AI_ENABLED).',
            ], 422);
        }

        if (! config('inbox.ai.draft_replies')) {
            return response()->json([
                'success' => false,
                'message' => 'Draft suggestions are switched off (INBOX_AI_DRAFT_REPLIES).',
            ], 422);
        }

        /*
         * Mark it queued BEFORE dispatching.
         *
         * This is the state the thread polls on and the composer shows progress from —
         * without it the request vanished into the queue and the only way to discover the
         * result was to close and reopen the thread. Writing it first, not after, means a
         * worker that picks the job up instantly still finds a consistent row.
         *
         * ai_draft is cleared at the same time: that is what makes "Try another" produce a
         * different draft rather than the job short-circuiting on the one already stored.
         */
        $email->forceFill([
            'ai_draft' => null,
            'ai_draft_status' => EmailDraftStatus::Queued,
            'ai_draft_requested_at' => now(),
        ])->save();

        DraftReplyForEmail::dispatch($email->id, $user->name);

        return response()->json([
            'success' => true,
            // The client flips straight into its "writing…" state on this rather than
            // waiting for the next poll to tell it something it already knows.
            'drafting' => true,
        ]);
    }

    /**
     * POST inbox/threads/{conversation}/summarise — summarise this thread, on request.
     *
     * Explicitly a person pressing a button. The previous behaviour generated one on every
     * thread open, which spent tokens on threads nobody needed summarised and, combined
     * with the one-minute poll, spent them repeatedly on the same thread.
     *
     * Re-summarising an already-current thread is refused rather than silently re-run: the
     * answer would be identical and the cost would not.
     */
    public function summarise(Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeThread($conversation->load('emails'), $user);

        if (! config('inbox.ai.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'AI is switched off for the inbox (INBOX_AI).',
            ], 422);
        }

        if (! config('inbox.ai.summarise')) {
            return response()->json([
                'success' => false,
                'message' => 'Thread summaries are switched off (INBOX_AI_SUMMARISE).',
            ], 422);
        }

        /*
         * Refuse when the caller cannot see the whole thread.
         *
         * The job excludes private and screened messages from the prompt, so the summary
         * itself is safe to show anyone — but a summary built from a subset, requested by
         * someone who cannot see the rest, is a summary of a thread they are not reading.
         * Let someone who can see all of it ask for one.
         */
        $partial = $conversation->emails->contains(
            fn (Email $e) => ($e->is_private && ! $this->access->canSeePrivate($user))
                || ($this->statusOf($e) === EmailStatus::PendingApprovalReceived->value
                    && ! $user->hasPermission(Email::APPROVE_RECEIVED_EMAILS_PERMISSION))
        );

        if ($partial) {
            return response()->json([
                'success' => false,
                'message' => 'Part of this thread is withheld from you, so it cannot be summarised here.',
            ], 422);
        }

        if ($conversation->hasCurrentAiSummary($conversation->emails->count())) {
            return response()->json([
                'success' => true,
                'summarising' => false,
                'message' => 'This summary is already up to date.',
            ]);
        }

        // Queued before dispatch, for the same reason as the draft request: this is what
        // the page polls on and shows progress from.
        $conversation->forceFill([
            'ai_summary_status' => EmailDraftStatus::Queued,
            'ai_summary_requested_at' => now(),
        ])->save();

        SummariseConversation::dispatch($conversation->id);

        return response()->json(['success' => true, 'summarising' => true]);
    }

    // ---------------------------------------------------------------- internals

    private function filters(Request $request): array
    {
        // Normalised up front, because the sort default below keys off it — reading the
        // raw input there would give an unrecognised view the wrong ordering while the
        // list itself fell back to needsReply.
        $view = $request->input('view', 'needsReply');
        $view = in_array($view, ThreadQuery::VIEWS, true) ? $view : 'needsReply';

        return [
            'view' => $view,
            'project_id' => $request->input('project_id'),
            'category_ids' => array_filter((array) $request->input('category_ids', [])),
            'search' => trim((string) $request->input('search', '')),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'unread_only' => $request->boolean('unread_only'),
            'overdue_only' => $request->boolean('overdue_only'),
            /*
             * An explicit sort wins; otherwise the view decides.
             *
             * This used to fall back to 'breach' for anything that was not literally
             * 'date', so a request that omitted the parameter got the queue ordering —
             * oldest first — whatever it was asking for. Only "Needs reply" wants that.
             * Mirrors defaultSortFor() on the client so a direct API call and the UI agree.
             */
            'sort' => match ($request->input('sort')) {
                'date' => 'date',
                'breach' => 'breach',
                default => $view === 'needsReply' ? 'breach' : 'date',
            },
            'per_page' => (int) $request->input('per_page', config('inbox.per_page', 25)),
        ];
    }

    private function statusOf(Email $email): string
    {
        return $email->status instanceof EmailStatus
            ? $email->status->value
            : strtolower((string) $email->status);
    }

    private function canSeeThread(Conversation $conversation, $user): bool
    {
        if ($conversation->project_id === null) {
            return $this->access->canSeeLeads($user);
        }

        return in_array($conversation->project_id, $this->access->projectIds($user), true);
    }

    private function authorizeThread(Conversation $conversation, $user): void
    {
        abort_unless($this->canSeeThread($conversation, $user), 403, 'You cannot open this thread.');
    }

    /**
     * Mark every email on the thread read for this user.
     *
     * Uses the same user_interactions row shape as Api\InboxController::markAsRead, so
     * read state is shared with the legacy page. `upsert` against the table's existing
     * unique key makes it idempotent without a select per email.
     */
    private function markRead(Conversation $conversation, $user): void
    {
        $rows = $conversation->emails->map(fn (Email $e) => [
            'user_id' => $user->id,
            'interactable_id' => $e->id,
            'interactable_type' => Email::class,
            'interaction_type' => 'read',
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($rows) {
            DB::table('user_interactions')->upsert(
                $rows,
                ['user_id', 'interactable_id', 'interactable_type', 'interaction_type'],
                ['updated_at']
            );
        }
    }

    private function markUnread(Conversation $conversation, $user): void
    {
        UserInteraction::query()
            ->where('user_id', $user->id)
            ->where('interactable_type', Email::class)
            ->where('interaction_type', 'read')
            ->whereIn('interactable_id', $conversation->emails->pluck('id'))
            ->delete();
    }

    /**
     * Approve every pending email on a thread this user is allowed to approve.
     *
     * Screened INBOUND mail is released (pending_approval_received → received) — it is
     * already delivered, approval just makes it readable by the team.
     *
     * Outbound drafts are NOT sent from here. Bulk-approving from a list means sending
     * client mail whose body the approver may not have read on this screen; the thread
     * view's single Approve &amp; send is the deliberate path for that. Bulk approve
     * therefore only clears the AI hold and leaves the email awaiting a person's send.
     *
     * @return int how many emails were actually changed
     */
    private function approveThread(Conversation $conversation, $user): int
    {
        $changed = 0;

        foreach ($conversation->emails as $email) {
            // EmailPolicy::editAndApprove, matching ThreadPresenter — using the looser
            // Email::$can_approve accessor here made the UI offer Release on threads this
            // loop then skipped, so the action reported "0 affected" with no explanation.
            if (! $user->can('editAndApprove', $email)) {
                continue;
            }

            // Same predicate the presenter uses to decide whether to show the button.
            if (! $this->presenter->isReleasable($email)) {
                continue;
            }

            $status = $email->status instanceof EmailStatus ? $email->status->value : (string) $email->status;

            if ($status === EmailStatus::PendingApprovalReceived->value) {
                $email->forceFill([
                    'status' => EmailStatus::Received->value,
                    'approved_by' => $user->id,
                ])->save();
                $changed++;

                if (config('inbox.ai.enabled')) {
                    DraftReplyForEmail::dispatch($email->id, $user->name);
                }

                continue;
            }

            // An AI hold, on whatever status the email is in. Clearing it only removes the
            // machine's objection — a person still sends.
            if ($email->ai_status === EmailAiStatus::Held) {
                $email->forceFill([
                    'ai_status' => EmailAiStatus::Approved,
                    'ai_reason' => null,
                ])->save();
                $changed++;
            }
        }

        return $changed;
    }
}
