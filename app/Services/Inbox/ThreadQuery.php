<?php

namespace App\Services\Inbox;

use App\Enums\EmailAiStatus;
use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Models\Conversation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the redesigned inbox's thread list.
 *
 * The old /inbox page paginates individual emails. This one paginates *conversations*,
 * because the design shows a thread per row ("4 messages") and opens into a thread view.
 * That single change is what most of the awkwardness below is paying for: every property
 * the list filters or sorts on is an aggregate over the conversation's emails.
 *
 * Those aggregates are computed as correlated subqueries rather than stored on the
 * conversations table. Denormalised columns would be faster but would need an observer on
 * every email write to stay correct, and a reply clock that silently drifts is worse than
 * one that costs a subquery. The 2026_08_19_100000 migration adds the composite indexes
 * that make them cheap.
 *
 * The reply clock, precisely:
 *   last_inbound_at  = newest received email in the thread
 *   last_outbound_at = newest sent email in the thread
 *   needs a reply    = last_inbound_at exists AND is newer than last_outbound_at
 *   due at           = last_inbound_at + config('inbox.sla_minutes')
 * It is wall-clock, not business hours — see config/inbox.php.
 */
class ThreadQuery
{
    public const VIEWS = [
        'needsReply',
        'new',
        'withAi',
        'approval',
        'received',
        'sent',
        'drafts',
        'all',
    ];

    public function __construct(
        private readonly InboxAccess $access,
        private readonly ReplyClock $clock,
    ) {}

    /**
     * @param  array{view?:string,project_id?:mixed,category_ids?:array,search?:string,
     *               from?:string,to?:string,unread_only?:bool,overdue_only?:bool,
     *               sort?:string,per_page?:int}  $filters
     */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $this->base($user);

        $this->applyView($query, $user, $filters['view'] ?? 'needsReply');
        $this->applyFilters($query, $user, $filters);
        $this->applySort($query, $filters['sort'] ?? 'breach');

        $perPage = (int) ($filters['per_page'] ?? config('inbox.per_page', 25));

        return $query->paginate(max(1, min($perPage, 100)));
    }

    /**
     * Per-view counts for the sidebar badges.
     *
     * Runs one COUNT per view against the same filtered base — eight small counts rather
     * than one clever grouped query, because each view's predicate is a different shape
     * and the union of them would be less readable than the thing it replaces.
     *
     * @return array<string,int>
     */
    public function counts(User $user, array $filters = []): array
    {
        $counts = [];

        foreach (self::VIEWS as $view) {
            $query = $this->base($user);
            $this->applyView($query, $user, $view);
            // Sidebar counts respect the project/category/search refinement so the badge
            // matches what clicking it will actually show.
            $this->applyFilters($query, $user, $filters);
            // count() clones without the select-subquery columns and their bindings,
            // so the aggregates in base() cost nothing here.
            $counts[$view] = $query->count('conversations.id');
        }

        return $counts;
    }

    /** How many visible threads are past the reply rule right now. */
    public function overdueCount(User $user, array $filters = []): int
    {
        $query = $this->base($user);
        $this->applyView($query, $user, 'needsReply');
        $this->applyFilters($query, $user, array_merge($filters, ['overdue_only' => true]));

        return $query->count('conversations.id');
    }

    /**
     * The base query: conversations the user may see, decorated with the aggregates the
     * views and sorts need. Everything downstream filters this.
     */
    public function base(User $user): Builder
    {
        $projectIds = $this->access->projectIds($user);
        $canSeeLeads = $this->access->canSeeLeads($user);
        $canSeePrivate = $this->access->canSeePrivate($user);

        $query = Conversation::query()
            ->select('conversations.*')
            ->where(function ($q) use ($projectIds, $canSeeLeads) {
                $q->whereIn('conversations.project_id', $projectIds ?: [0]);
                if ($canSeeLeads) {
                    $q->orWhereNull('conversations.project_id');
                }
            });

        // A conversation whose every email is private is invisible to someone without
        // view_private_emails — otherwise the list would show a thread they cannot open.
        if (! $canSeePrivate) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('emails')
                    ->whereColumn('emails.conversation_id', 'conversations.id')
                    ->whereNull('emails.deleted_at')
                    ->where(function ($p) {
                        $p->whereNull('emails.is_private')->orWhere('emails.is_private', false);
                    });
            });
        }

        $visible = fn ($q) => $q
            ->whereColumn('emails.conversation_id', 'conversations.id')
            ->whereNull('emails.deleted_at');

        $query
            ->selectSub(
                DB::table('emails')
                    ->selectRaw('MAX(COALESCE(emails.sent_at, emails.created_at))')
                    ->tap($visible)
                    ->where('emails.type', EmailType::Received->value),
                'last_inbound_at'
            )
            ->selectSub(
                DB::table('emails')
                    ->selectRaw('MAX(COALESCE(emails.sent_at, emails.created_at))')
                    ->tap($visible)
                    ->where('emails.type', EmailType::Sent->value)
                    // Not a bare `= 'sent'`. `approved` is a legacy terminal status that
                    // real rows still carry, and treating it as undelivered reclassified
                    // every one of those threads as unanswered. ReplyClock owns the list
                    // so this and ThreadPresenter cannot drift. See config/inbox.php.
                    ->whereIn('emails.status', $this->clock->deliveredStatuses()),
                'last_outbound_at'
            )
            ->selectSub(
                DB::table('emails')
                    ->selectRaw('MAX(COALESCE(emails.sent_at, emails.created_at))')
                    ->tap($visible),
                'last_message_at'
            )
            ->selectSub(
                DB::table('emails')->selectRaw('COUNT(*)')->tap($visible),
                'email_count'
            )
            ->selectSub(
                DB::table('emails')
                    ->selectRaw('COUNT(*)')
                    ->tap($visible)
                    ->whereNotExists(function ($q) use ($user) {
                        $q->select(DB::raw(1))
                            ->from('user_interactions')
                            ->whereColumn('user_interactions.interactable_id', 'emails.id')
                            ->where('user_interactions.interactable_type', \App\Models\Email::class)
                            ->where('user_interactions.user_id', $user->id)
                            ->where('user_interactions.interaction_type', 'read');
                    }),
                'unread_count'
            );

        return $query;
    }

    /**
     * The sidebar views. These are thread-level questions, so each is "does the thread
     * contain an email that…" rather than a status on the row itself — a thread can be
     * both awaiting approval and overdue for a reply, and appears in both.
     */
    private function applyView(Builder $query, User $user, string $view): void
    {
        $hasEmail = fn (Builder $q, callable $where) => $q->whereExists(function ($sub) use ($where) {
            $sub->select(DB::raw(1))
                ->from('emails')
                ->whereColumn('emails.conversation_id', 'conversations.id')
                ->whereNull('emails.deleted_at');
            $where($sub);
        });

        switch ($view) {
            case 'needsReply':
                $this->whereNeedsReply($query);
                break;

            case 'new':
                $hasEmail($query, fn ($q) => $q
                    // Exclude UNSENT OUTBOUND drafts only. `status = draft` on an inbound
                    // row means "arrived, not yet processed" — that is how
                    // EmailReceiveController stores ordinary client mail — so excluding
                    // every draft would have hidden all unread client email from the one
                    // view whose entire job is showing it.
                    ->whereNot(fn ($d) => $d
                        ->where('emails.type', EmailType::Sent->value)
                        ->where('emails.status', EmailStatus::Draft->value))
                    ->whereNotExists(function ($r) use ($user) {
                        $r->select(DB::raw(1))
                            ->from('user_interactions')
                            ->whereColumn('user_interactions.interactable_id', 'emails.id')
                            ->where('user_interactions.interactable_type', \App\Models\Email::class)
                            ->where('user_interactions.user_id', $user->id)
                            ->where('user_interactions.interaction_type', 'read');
                    }));
                break;

            case 'withAi':
                $hasEmail($query, fn ($q) => $q->whereIn('emails.ai_status', [
                    EmailAiStatus::Queued->value,
                    EmailAiStatus::Checking->value,
                ]));
                break;

            case 'approval':
                $hasEmail($query, fn ($q) => $q->whereIn('emails.status', [
                    EmailStatus::PendingApproval->value,
                    EmailStatus::PendingApprovalReceived->value,
                ]));
                break;

            case 'received':
                $hasEmail($query, fn ($q) => $q->where('emails.type', EmailType::Received->value));
                break;

            case 'sent':
                // Same delivered-status list the reply clock uses, so a legacy `approved`
                // email cannot count as answering a thread while being absent from the
                // view that claims to list everything we have sent.
                $hasEmail($query, fn ($q) => $q
                    ->where('emails.type', EmailType::Sent->value)
                    ->whereIn('emails.status', $this->clock->deliveredStatuses()));
                break;

            case 'drafts':
                // Outbound only, for the same reason as above — without the type check
                // this listed every thread containing a client email.
                $hasEmail($query, fn ($q) => $q
                    ->where('emails.type', EmailType::Sent->value)
                    ->where('emails.status', EmailStatus::Draft->value));
                break;

            case 'all':
            default:
                break;
        }
    }

    /**
     * "An inbound message is newer than our newest reply."
     *
     * Written as raw correlated subqueries rather than reusing the selectSub aliases
     * because MySQL will not accept a select alias in a WHERE clause.
     */
    private function whereNeedsReply(Builder $query): void
    {
        $inbound = $this->clock->inboundSql();
        $outbound = $this->clock->outboundSql();

        $query->whereRaw("($inbound) IS NOT NULL")
            ->whereRaw("(($outbound) IS NULL OR ($outbound) < ($inbound))");

        /*
         * The cutover.
         *
         * Everything above is a correct reading of the emails table; this line is about
         * what the table does not contain. Replies sent from the Gmail web UI were never
         * ingested — the poller has only ever asked for `is:inbox` — so an old thread
         * reads as unanswered whether or not anyone answered it. Rather than present a
         * guess as a fact, the clock declines to judge anything whose newest client
         * message predates the cutover.
         *
         * Filter only: nothing is written, nothing is marked handled, and clearing
         * INBOX_REPLY_CLOCK_SINCE brings every one of those threads straight back. They
         * remain fully visible in Received and All mail throughout — this narrows one
         * view, it does not hide mail. See ReplyClock and config/inbox.php.
         */
        if ($since = $this->clock->since()) {
            $query->whereRaw("($inbound) >= ?", [$since->toDateTimeString()]);
        }
    }

    private function applyFilters(Builder $query, User $user, array $filters): void
    {
        if (! empty($filters['project_id']) && $filters['project_id'] !== 'all') {
            if ($filters['project_id'] === 'leads') {
                $query->whereNull('conversations.project_id');
            } else {
                $query->where('conversations.project_id', (int) $filters['project_id']);
            }
        }

        // Categories live on emails, not conversations. A thread matches when it has an
        // email in the category; with several selected it must match all of them —
        // matching the legacy page's additive behaviour rather than quietly widening it.
        $categoryIds = array_filter((array) ($filters['category_ids'] ?? []));
        foreach ($categoryIds as $categoryId) {
            $query->whereExists(function ($q) use ($categoryId) {
                $q->select(DB::raw(1))
                    ->from('emails')
                    ->join('categorizables', function ($join) {
                        $join->on('categorizables.categorizable_id', '=', 'emails.id')
                            ->where('categorizables.categorizable_type', '=', \App\Models\Email::class);
                    })
                    ->whereColumn('emails.conversation_id', 'conversations.id')
                    ->whereNull('emails.deleted_at')
                    ->where('categorizables.category_id', (int) $categoryId);
            });
        }

        if (! empty($filters['search'])) {
            $raw = trim($filters['search']);
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $raw).'%';

            // "OZE123" is the number shown on every message and in global search results,
            // and Email::getEmailNumberAttribute builds it as 'OZE'.id — so the lookup is
            // an id equality, not a LIKE. Only fires when the WHOLE term is that shape:
            // a bare number inside a longer query ("invoice 500") is a text search.
            $emailId = preg_match('/^oze[\s-]*(\d+)$/i', $raw, $m) || preg_match('/^(\d+)$/', $raw, $m)
                ? (int) $m[1]
                : null;

            $query->where(function ($q) use ($term, $emailId) {
                $q->where('conversations.subject', 'like', $term)
                    ->when($emailId, fn ($w) => $w->orWhereExists(function ($sub) use ($emailId) {
                        $sub->select(DB::raw(1))
                            ->from('emails')
                            ->whereColumn('emails.conversation_id', 'conversations.id')
                            ->whereNull('emails.deleted_at')
                            ->where('emails.id', $emailId);
                    }))
                    ->orWhereExists(function ($sub) use ($term) {
                        $sub->select(DB::raw(1))
                            ->from('emails')
                            ->whereColumn('emails.conversation_id', 'conversations.id')
                            ->whereNull('emails.deleted_at')
                            ->where(function ($p) use ($term) {
                                $p->where('emails.subject', 'like', $term)
                                    ->orWhere('emails.body', 'like', $term)
                                    // A templated email has body = null — its text lives in
                                    // the template. Without this, searching for words the
                                    // client can plainly read in the thread finds nothing.
                                    // Matches the template's own text, not the filled-in
                                    // placeholder values, which are only in template_data.
                                    ->orWhereExists(function ($tpl) use ($term) {
                                        $tpl->select(DB::raw(1))
                                            ->from('email_templates')
                                            ->whereColumn('email_templates.id', 'emails.template_id')
                                            ->where(function ($t) use ($term) {
                                                $t->where('email_templates.subject', 'like', $term)
                                                    ->orWhere('email_templates.body_html', 'like', $term);
                                            });
                                    });
                            });
                    });
            });
        }

        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $start = ! empty($filters['from'])
                ? Carbon::parse($filters['from'])->startOfDay()
                : Carbon::createFromTimestamp(0);
            $end = ! empty($filters['to'])
                ? Carbon::parse($filters['to'])->endOfDay()
                : Carbon::now()->addCentury();

            $query->whereExists(function ($q) use ($start, $end) {
                $q->select(DB::raw(1))
                    ->from('emails')
                    ->whereColumn('emails.conversation_id', 'conversations.id')
                    ->whereNull('emails.deleted_at')
                    ->whereBetween(DB::raw('COALESCE(emails.sent_at, emails.created_at)'), [$start, $end]);
            });
        }

        if (! empty($filters['unread_only'])) {
            $query->whereExists(function ($q) use ($user) {
                $q->select(DB::raw(1))
                    ->from('emails')
                    ->whereColumn('emails.conversation_id', 'conversations.id')
                    ->whereNull('emails.deleted_at')
                    ->whereNotExists(function ($r) use ($user) {
                        $r->select(DB::raw(1))
                            ->from('user_interactions')
                            ->whereColumn('user_interactions.interactable_id', 'emails.id')
                            ->where('user_interactions.interactable_type', \App\Models\Email::class)
                            ->where('user_interactions.user_id', $user->id)
                            ->where('user_interactions.interaction_type', 'read');
                    });
            });
        }

        if (! empty($filters['overdue_only'])) {
            $this->whereNeedsReply($query);

            $cutoff = Carbon::now()
                ->subMinutes((int) config('inbox.sla_minutes', 60))
                ->toDateTimeString();

            $inbound = $this->clock->inboundSql();
            $query->whereRaw("($inbound) <= ?", [$cutoff]);
        }
    }

    /**
     * "Time to reply" puts the thread closest to breaching first: unanswered threads
     * ordered by oldest inbound message, then everything else by recency. "Newest first"
     * is a plain recency sort.
     */
    /**
     * Two orders, and which one a view gets is decided by the client (defaultSortFor).
     *
     * `breach` — the queue order, for "Needs reply". Unanswered threads first, and within
     * those the one that has been waiting LONGEST at the top: first come, first replied.
     * Ascending by the client's oldest unanswered message is the point of it.
     *
     * `date` — the log order, newest first. This is what every other view wants and it is
     * now their default. It was not: `breach` was the default everywhere, so Sent,
     * Received and All mail all opened on the oldest thread in the system. Correct for a
     * queue, wrong for a log, and the reason the whole inbox looked like it was showing
     * ancient mail.
     *
     * Both end on `conversations.id` so the order is total. Without a unique tiebreaker,
     * two threads sharing a timestamp can swap places between page 1 and page 2 — one row
     * appears twice and another is never seen at all.
     */
    private function applySort(Builder $query, string $sort): void
    {
        $inbound = $this->clock->inboundSql();
        $outbound = $this->clock->outboundSql();

        if ($sort === 'breach') {
            $query
                ->orderByRaw(
                    "CASE WHEN ($inbound) IS NOT NULL AND (($outbound) IS NULL OR ($outbound) < ($inbound))"
                    .' THEN 0 ELSE 1 END ASC'
                )
                // Longest-waiting client at the top.
                ->orderByRaw("($inbound) ASC")
                // Answered threads fall through to here, and among those recency is what
                // matters — nobody is waiting on them.
                ->orderByRaw($this->newestFirstSql())
                ->orderBy('conversations.id', 'desc');

            return;
        }

        $query
            ->orderByRaw($this->newestFirstSql())
            ->orderBy('conversations.id', 'desc');
    }

    /**
     * "Newest first", keyed on the newest MESSAGE rather than `last_activity_at`.
     *
     * `last_activity_at` is a denormalised column maintained by hand in a dozen create
     * paths, and anything that writes an email without remembering to bump it leaves the
     * thread stranded at its old position — which looks exactly like the list being sorted
     * wrongly. The correlated subquery cannot drift because it reads the emails.
     *
     * It falls back to `last_activity_at` and then `updated_at` so a conversation with no
     * emails still sorts somewhere sensible instead of collapsing to NULL and sinking to
     * the bottom.
     *
     * A subquery rather than the `last_message_at` select alias: ordering by an alias
     * works in MySQL but not everywhere, and this is on the path of every inbox request —
     * not somewhere to trade a portability question for a saved subquery the optimiser
     * already recognises from the SELECT.
     */
    private function newestFirstSql(): string
    {
        $newest = 'SELECT MAX(COALESCE(e.sent_at, e.created_at)) FROM emails e'
            .' WHERE e.conversation_id = conversations.id AND e.deleted_at IS NULL';

        return "COALESCE(({$newest}), conversations.last_activity_at, conversations.updated_at) DESC";
    }
}
