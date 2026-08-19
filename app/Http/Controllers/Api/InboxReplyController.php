<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Http\Controllers\Controller;
use App\Jobs\Inbox\CheckEmailWithAi;
use App\Models\Conversation;
use App\Models\Email;
use App\Services\Inbox\InboxAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Replying, replying-all and forwarding from inside a thread.
 *
 * This endpoint only ever CREATES the email — as a draft, or as pending_approval. It
 * never sends. Sending stays with the existing, proven path:
 * `POST /api/emails/{email}/edit-and-approve` on Api\EmailController, which renders the
 * template, resolves recipients and hands off to GmailService.
 *
 * What is stored is ONLY what the person typed. The quoted conversation and the Gmail
 * threading headers are added at send time by App\Services\Inbox\ReplyThreading, keyed on
 * the in_reply_to_email_id recorded below. That split is the point: emails.body is what
 * the AI checker reads, so storing the quote would re-send the entire thread to the model
 * on every reply — cost that grows with the thread and buys nothing, since the checker is
 * judging the new text. The client still receives the full quoted chain.
 *
 * That split is on purpose. Duplicating the send logic here would mean two code paths
 * that must agree about templating, recipient resolution and Gmail threading forever, and
 * the redesign would own a way for client mail to go out wrong. So the client makes two
 * calls to send: create here, then approve there. Each is independently authorised, and a
 * failure between them leaves a recoverable draft rather than a half-sent email.
 *
 * The AI checker, if enabled, is attached at creation — see CheckEmailWithAi for the
 * state machine and why an approved check still does not send by itself.
 */
class InboxReplyController extends Controller
{
    public function __construct(private readonly InboxAccess $access) {}

    /** POST /api/inbox/threads/{conversation}/reply */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $conversation->load(['emails', 'conversable']);

        abort_unless($this->canSeeThread($conversation, $user), 403, 'You cannot reply to this thread.');

        // A thread holding a message this person may not read must not become a reply
        // they compose — they would be answering something they cannot see, and quoting
        // it back to the client.
        if (! $this->access->canSeePrivate($user)
            && $conversation->emails->contains(fn (Email $e) => (bool) $e->is_private)) {
            abort(403, 'This thread holds a private message, so replies are handled by a manager.');
        }

        $data = $request->validate([
            'mode' => ['required', Rule::in(['reply', 'replyAll', 'forward'])],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            // Only honoured for `forward`. For reply/replyAll the server resolves the
            // recipients itself — see resolveRecipients() below for why.
            'to' => ['nullable', 'array'],
            'to.*' => ['email'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email'],
            'bcc' => ['nullable', 'array'],
            'bcc.*' => ['email'],
            // The message being answered. Drives both the Gmail threading headers and the
            // quoted chain at send time; validated against this conversation below so a
            // reply cannot be linked to a message on someone else's thread.
            'in_reply_to_email_id' => ['nullable', 'integer', 'exists:emails,id'],
            // A draft is parked; otherwise it is submitted and picks up the normal
            // approval rules for this user.
            'save_as_draft' => ['sometimes', 'boolean'],
        ]);

        // Forwarding to somewhere outside the conversation is a different, riskier action
        // than replying to the person already on it, so it is gated separately.
        if ($data['mode'] === 'forward' && ! $this->access->isManager($user)) {
            abort(403, 'Only a manager can forward a client thread.');
        }

        $status = ($data['save_as_draft'] ?? false)
            ? EmailStatus::Draft
            : EmailStatus::PendingApproval;

        // Default to the newest inbound message on this thread — that is what a reply
        // answers. Anything explicitly passed must belong to THIS conversation; accepting
        // an arbitrary email id would quote another thread's messages into this one.
        $parentId = $data['in_reply_to_email_id'] ?? null;

        if ($parentId && ! $conversation->emails->contains('id', (int) $parentId)) {
            abort(422, 'That message is not part of this thread.');
        }

        if (! $parentId) {
            $parentId = $conversation->emails
                ->sortBy(fn (Email $e) => ($e->sent_at ?? $e->created_at)?->timestamp ?? 0)
                ->last(fn (Email $e) => ($e->type instanceof EmailType ? $e->type->value : (string) $e->type)
                    === EmailType::Received->value)?->id;
        }

        $to = $this->resolveRecipients($conversation, $data['mode'], $data['to'] ?? []);

        if (empty($to)) {
            abort(422, 'There is no address on file to send this to.');
        }

        $email = new Email([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => \App\Models\User::class,
            'to' => $to,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => $status->value,
            'type' => EmailType::Sent->value,
            'in_reply_to_email_id' => $parentId,
            // Deliberately no template_id / template_data. Replies composed here are
            // plain-body ("custom") emails, and the send path branches on template_id:
            // EmailController::editAndApprove would take the templated branch and call
            // json_decode() on template_data, which is already cast to an array —
            // a TypeError its catch block does not handle. Template composing stays on
            // the classic page until it is ported properly.
        ]);
        $email->save();

        $conversation->forceFill(['last_activity_at' => now()])->save();

        // Cc/Bcc have no column on `emails` — the schema only has `to`. Rather than
        // silently drop them, they are recorded on the thread as an internal note so the
        // team can see who was copied, and the send path is unchanged. Giving the emails
        // table cc/bcc columns is the real fix and belongs in its own change.
        $extra = array_merge($data['cc'] ?? [], $data['bcc'] ?? []);
        if ($extra) {
            $conversation->notes()->create([
                'user_id' => $user->id,
                'content' => 'Cc/Bcc requested on the reply "'.$data['subject'].'": '
                    .implode(', ', $extra)
                    .' — not yet supported by the send path, add them manually if needed.',
            ]);
        }

        if ($status === EmailStatus::PendingApproval
            && config('inbox.ai.enabled')
            && config('inbox.ai.check_outbound')) {
            $email->forceFill(['ai_status' => \App\Enums\EmailAiStatus::Queued])->save();
            CheckEmailWithAi::dispatch($email->id);
        }

        return response()->json([
            'data' => [
                'email_id' => $email->id,
                'status' => $status->value,
                'conversation_id' => $conversation->id,
                // True when the caller may finish the send themselves by posting to
                // /api/emails/{id}/edit-and-approve. Checked against the SAME policy that
                // endpoint authorises against, not the looser can_approve accessor —
                // otherwise a lead thread reports can_send and then 403s, leaving a reply
                // that looks sent and is not.
                'can_send' => $status === EmailStatus::PendingApproval
                    && $user->can('editAndApprove', $email->fresh()),
                'awaiting_ai' => $email->ai_status?->isPending() ?? false,
            ],
        ], 201);
    }

    /**
     * Recipients to pre-fill the reply box with, for each mode.
     *
     * Client email addresses are masked for anyone without `edit_clients` — the Client
     * model hides `email` from serialisation for exactly those users, and this endpoint
     * would otherwise hand the address straight back through a different door. The reply
     * box shows the masked label; store() resolves the real address server-side, so a
     * masked recipient still receives the reply.
     */
    public function recipients(Request $request, Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $conversation->load(['emails', 'conversable']);

        abort_unless($this->canSeeThread($conversation, $user), 403);

        $lastInbound = $conversation->emails
            ->sortBy(fn (Email $e) => ($e->sent_at ?? $e->created_at)?->timestamp ?? 0)
            ->last(fn (Email $e) => ($e->type instanceof EmailType ? $e->type->value : (string) $e->type)
                === EmailType::Received->value);

        $mask = ! $user->hasPermission('edit_clients');
        $show = fn (array $addresses) => array_map(
            fn ($a) => $mask ? $this->maskAddress($a) : $a,
            $addresses
        );

        return response()->json([
            'reply' => $show($this->resolveRecipients($conversation, 'reply')),
            'replyAll' => $show($this->resolveRecipients($conversation, 'replyAll')),
            'forward' => [],
            'subject' => $this->replySubject($conversation),
            'last_inbound_email_id' => $lastInbound?->id,
            'masked' => $mask,
        ]);
    }

    /**
     * Who a reply actually goes to.
     *
     * For reply and replyAll this is derived from the conversation, and whatever the
     * client posted is ignored. Two reasons, and both matter:
     *  - The reply box may be showing masked addresses, so the posted value can be a
     *    label rather than an address.
     *  - Trusting a posted `to` on a thread-scoped reply would let anyone who can open a
     *    thread redirect it to an address of their choosing. Forwarding is the deliberate
     *    way to send a thread somewhere new, and it is manager-only.
     *
     * @param  array<string>  $requested  only consulted for `forward`
     * @return array<string>
     */
    private function resolveRecipients(Conversation $conversation, string $mode, array $requested = []): array
    {
        if ($mode === 'forward') {
            return array_values(array_unique(array_filter(
                $requested,
                fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)
            )));
        }

        // getRawOriginal, because Client hides `email` for users without edit_clients and
        // we need the real address to actually deliver the reply.
        $conversable = $conversation->conversable;
        $primary = $conversable
            ? ($conversable->getRawOriginal('email') ?? $conversable->email ?? null)
            : null;

        if ($mode === 'reply') {
            return array_values(array_filter([$primary]));
        }

        $everyone = $conversation->emails
            ->flatMap(fn (Email $e) => is_array($e->to) ? $e->to : array_filter([$e->to]))
            ->filter(fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))
            ->all();

        return array_values(array_unique(array_filter(array_merge([$primary], $everyone))));
    }

    /** "a****n@example.com" — enough to recognise, not enough to harvest. */
    private function maskAddress(string $address): string
    {
        [$local, $domain] = array_pad(explode('@', $address, 2), 2, '');

        if ($domain === '') {
            return $address;
        }

        $visible = mb_strlen($local) <= 2
            ? mb_substr($local, 0, 1)
            : mb_substr($local, 0, 1).str_repeat('*', min(4, mb_strlen($local) - 2)).mb_substr($local, -1);

        return $visible.'@'.$domain;
    }

    private function replySubject(Conversation $conversation): string
    {
        $subject = $conversation->subject
            ?: ($conversation->emails->last()?->subject ?? '');

        return preg_match('/^re:\s*/i', $subject) ? $subject : 'Re: '.$subject;
    }

    private function canSeeThread(Conversation $conversation, $user): bool
    {
        if ($conversation->project_id === null) {
            return $this->access->canSeeLeads($user);
        }

        return in_array($conversation->project_id, $this->access->projectIds($user), true);
    }
}
