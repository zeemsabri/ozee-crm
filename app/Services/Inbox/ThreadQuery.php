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

    public function __construct(private readonly InboxAccess $access) {}

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
                    ->where('emails.status', EmailStatus::Sent->value),
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
                    ->where('emails.status', '!=', EmailStatus::Draft->value)
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
                $hasEmail($query, fn ($q) => $q
                    ->where('emails.type', EmailType::Sent->value)
                    ->where('emails.status', EmailStatus::Sent->value));
                break;

            case 'drafts':
                $hasEmail($query, fn ($q) => $q->where('emails.status', EmailStatus::Draft->value));
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
        $inbound = $this->latestSql(EmailType::Received->value, false);
        $outbound = $this->latestSql(EmailType::Sent->value, true);

        $query->whereRaw("($inbound) IS NOT NULL")
            ->whereRaw("(($outbound) IS NULL OR ($outbound) < ($inbound))");
    }

    /** SQL for "newest inbound/outbound timestamp in this conversation". */
    private function latestSql(string $type, bool $sentOnly): string
    {
        $statusClause = $sentOnly
            ? " AND e.status = '".EmailStatus::Sent->value."'"
            : '';

        return "SELECT MAX(COALESCE(e.sent_at, e.created_at)) FROM emails e"
            ." WHERE e.conversation_id = conversations.id"
            ." AND e.deleted_at IS NULL"
            ." AND e.type = '{$type}'{$statusClause}";
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
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($filters['search'])).'%';
            $query->where(function ($q) use ($term) {
                $q->where('conversations.subject', 'like', $term)
                    ->orWhereExists(function ($sub) use ($term) {
                        $sub->select(DB::raw(1))
                            ->from('emails')
                            ->whereColumn('emails.conversation_id', 'conversations.id')
                            ->whereNull('emails.deleted_at')
                            ->where(function ($p) use ($term) {
                                $p->where('emails.subject', 'like', $term)
                                    ->orWhere('emails.body', 'like', $term);
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

            $inbound = $this->latestSql(EmailType::Received->value, false);
            $query->whereRaw("($inbound) <= ?", [$cutoff]);
        }
    }

    /**
     * "Time to reply" puts the thread closest to breaching first: unanswered threads
     * ordered by oldest inbound message, then everything else by recency. "Newest first"
     * is a plain recency sort.
     */
    private function applySort(Builder $query, string $sort): void
    {
        $inbound = $this->latestSql(EmailType::Received->value, false);
        $outbound = $this->latestSql(EmailType::Sent->value, true);

        if ($sort === 'breach') {
            $query
                ->orderByRaw(
                    "CASE WHEN ($inbound) IS NOT NULL AND (($outbound) IS NULL OR ($outbound) < ($inbound))"
                    .' THEN 0 ELSE 1 END ASC'
                )
                ->orderByRaw("($inbound) ASC")
                ->orderByRaw('conversations.last_activity_at DESC')
                ->orderBy('conversations.id', 'desc');

            return;
        }

        $query
            ->orderByRaw('COALESCE(conversations.last_activity_at, conversations.updated_at) DESC')
            ->orderBy('conversations.id', 'desc');
    }
}
