<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmailType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Email;
use App\Services\Inbox\Correspondent;
use App\Services\Inbox\InboxAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The bin: deleted mail, and putting it back.
 *
 * ## Why this is a list of EMAILS, not threads
 *
 * Everywhere else the inbox is thread-shaped, and this deliberately is not. `ThreadQuery`
 * excludes soft-deleted rows in nine separate places — every aggregate, every view, and
 * the base filter that hides a conversation with nothing live left in it — because that is
 * what makes the rest of the inbox correct. A "deleted threads" view would need a
 * withTrashed variant of all of it, and the two would drift.
 *
 * It is also the truer shape. Deleting one message out of a live thread is the common
 * case; that thread is not deleted, and listing it as though it were would be a lie. What
 * was deleted is a message, so that is what this lists.
 *
 * ## Permission
 *
 * `InboxAccess::canSeeDeleted` — the same ability as deleting. See the note there for why
 * there is no separate "view the bin" permission.
 *
 * Thread visibility still applies on top: the bin only ever shows deleted mail from
 * conversations this person could have opened, and a private message stays hidden from
 * anyone without `view_private_emails`. Deleting something does not make it readable.
 */
class InboxDeletedController extends Controller
{
    public function __construct(
        private readonly InboxAccess $access,
        private readonly Correspondent $correspondent,
    ) {}

    /** GET /api/inbox/deleted — what is in the bin. */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        abort_unless($this->access->canSeeDeleted($user), 403, 'You do not have permission to view deleted mail.');

        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $projectIds = $this->access->projectIds($user);
        $canSeeLeads = $this->access->canSeeLeads($user);
        $canSeePrivate = $this->access->canSeePrivate($user);

        $query = Email::onlyTrashed()
            // No "deleted by": `emails` has deleted_at and no deleted_by column, and
            // Email is not on Spatie's activity log either — see the audit-gap note in
            // project memory. The bin can say WHEN, not WHO.
            ->with(['conversation.project', 'conversation.conversable', 'sender'])
            /*
             * Same visibility rule as the thread list, expressed against the conversation
             * rather than re-derived: a project the user is on, or a lead thread when they
             * may see leads. Without this the bin would be a way around project scoping.
             */
            ->whereHas('conversation', function ($q) use ($projectIds, $canSeeLeads) {
                /*
                 * Wrapped, and it has to be. whereHas appends the closure's conditions at
                 * the same level as the relation's own key constraint, so a bare
                 * `orWhereNull` here would break out of that AND and match every
                 * project-less conversation in the database regardless of who is asking.
                 */
                $q->where(function ($w) use ($projectIds, $canSeeLeads) {
                    $w->whereIn('conversations.project_id', $projectIds ?: [0]);

                    if ($canSeeLeads) {
                        $w->orWhereNull('conversations.project_id');
                    }
                });
            });

        // Private stays private. Deleting a message is not a way to read one.
        if (! $canSeePrivate) {
            $query->where(fn ($q) => $q->whereNull('is_private')->orWhere('is_private', false));
        }

        if ($term = trim((string) ($data['search'] ?? ''))) {
            $query->where(fn ($q) => $q
                ->where('subject', 'like', "%{$term}%")
                ->orWhereHas('conversation', fn ($c) => $c->where('subject', 'like', "%{$term}%")));
        }

        $rows = $query
            ->orderByDesc('deleted_at')
            ->paginate($data['per_page'] ?? (int) config('inbox.per_page', 25));

        return response()->json([
            'data' => collect($rows->items())->map(fn (Email $e) => $this->present($e))->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    /**
     * POST /api/inbox/emails/{email}/restore — put one message back.
     *
     * `withTrashed` on the route binding, because the default binding cannot find a
     * soft-deleted row and would 404 every restore.
     */
    public function restore(int $emailId): JsonResponse
    {
        $user = Auth::user();

        abort_unless($this->access->canSeeDeleted($user), 403, 'You do not have permission to restore mail.');

        $email = Email::withTrashed()->findOrFail($emailId);

        abort_unless($this->canSee($email, $user), 403, 'You cannot restore that message.');

        if (! $email->trashed()) {
            // Not an error worth failing on — someone restored it in another tab, and the
            // end state is the one that was asked for.
            return response()->json(['success' => true, 'already_restored' => true]);
        }

        $email->restore();

        return response()->json([
            'success' => true,
            'conversation_id' => $email->conversation_id,
        ]);
    }

    /**
     * Could this person have opened the thread this message is on?
     *
     * Deliberately the same three questions the list asks, so a row that appears in the
     * bin can always be restored and one that cannot never appears.
     */
    private function canSee(Email $email, $user): bool
    {
        $conversation = $email->conversation;

        if (! $conversation instanceof Conversation) {
            return false;
        }

        if ($email->is_private && ! $this->access->canSeePrivate($user)) {
            return false;
        }

        if ($conversation->project_id === null) {
            return $this->access->canSeeLeads($user);
        }

        return in_array($conversation->project_id, $this->access->projectIds($user), true);
    }

    /**
     * One row.
     *
     * No body. The bin answers "what did I delete, and do I want it back" — rendering the
     * message content would mean running it through EmailHtml and the redaction rules for
     * every row on the page, to show something the person is about to restore anyway and
     * can then read in its thread.
     */
    private function present(Email $email): array
    {
        $conversation = $email->conversation;
        $type = $email->type instanceof EmailType ? $email->type->value : (string) $email->type;

        return [
            'id' => $email->id,
            'subject' => $email->subject ?: ($conversation?->subject ?: '(no subject)'),
            'direction' => $type === EmailType::Received->value ? 'in' : 'out',
            'status' => $email->status?->value ?? (string) $email->status,
            'is_private' => (bool) $email->is_private,
            'deleted_at' => $email->deleted_at?->toIso8601String(),
            'sent_at' => ($email->sent_at ?? $email->created_at)?->toIso8601String(),

            // A name, never an address — the rule the whole timeline follows. See
            // Correspondent's class docblock.
            'correspondent' => $conversation
                ? $this->correspondent->labelFor($conversation)
                : 'Unknown sender',
            'project' => $conversation?->project?->name,

            'conversation' => [
                'id' => $email->conversation_id,
                'subject' => $conversation?->subject,
                /*
                 * Whether the thread still exists as far as the inbox is concerned. False
                 * means every message on it is deleted, so the thread is not in the list
                 * either (ThreadQuery::base) — restoring this message is what brings the
                 * whole thread back, and the UI says so instead of offering a dead link.
                 */
                'live' => $conversation
                    ? $conversation->emails()->whereNull('deleted_at')->exists()
                    : false,
            ],

            /*
             * Only the local copy is recoverable. If the Gmail copy was binned in the same
             * action it is gone for good, and restoring here does not bring it back —
             * worth saying on the row rather than letting someone assume otherwise.
             *
             * `message_id` is Gmail's API id: present on inbound mail, and back-filled
             * onto our own sent mail by IngestSentMail. Its absence is not proof the Gmail
             * copy was deleted, so the wording is about what restore does, not about what
             * Gmail holds.
             */
            'restores_local_copy_only' => true,
        ];
    }
}
