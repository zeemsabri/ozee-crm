<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Email;
use App\Services\Inbox\BlockComposition;
use App\Services\Inbox\BlockRenderer;
use App\Services\Inbox\Correspondent;
use App\Services\Inbox\EmailImageStore;
use App\Services\Inbox\InboxAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Replying, replying-all and forwarding from inside a thread.
 *
 * ## This endpoint submits. It does not send, and it does not park.
 *
 * A reply is written as `status = draft`, `type = sent` and nothing else happens here.
 * That row is the trigger for the automation the whole business already runs on:
 *
 *   Email::created
 *     → GlobalModelEventSubscriber (config/automation.php allow-lists Email)
 *       → WorkflowTriggerEvent('email.created')
 *         → WorkflowTriggerListener → RunWorkflowJob → WorkflowEngineService
 *           → workflow 17, gated on status == 'draft' AND type == 'sent'
 *             → AI_PROMPT "Email Approval Analysis"
 *               → approved  → ACTION PROCESS_EMAIL → ProcessDraftEmailJob → Gmail
 *               → refused   → UPDATE_RECORD status = 'pending_approval' (a human decides)
 *
 * An earlier version of this controller created the reply at `pending_approval` and had
 * the client immediately POST to `emails/{id}/edit-and-approve`. That worked, and it was
 * wrong: it walked straight past the AI review that every email sent from the classic
 * inbox goes through, so the redesign would have been the one way to get unreviewed mail
 * to a client. Creating at `draft` is not a lesser version of that — it is the same thing
 * the classic templated composer does (EmailController::storeTemplatedEmail defaults to
 * `Email::STATUS_DRAFT`), which is precisely the point: one submission path, one review.
 *
 * ## There is no "save as draft"
 *
 * Worth saying plainly, because the word invites the opposite assumption. In this schema
 * `draft` does not mean "parked, not finished" — it means "submitted, awaiting the
 * automation". A parked draft would have to be a row that `Email::created` does not
 * reach, and no such state exists. So the composer has no Save-draft button, and this
 * endpoint has no `save_as_draft` parameter; adding either would hand someone a button
 * labelled "save" that mails a client.
 *
 * ## What is stored, and what is added later
 *
 * Only what the person typed. The quoted conversation and the Gmail threading headers are
 * added at send time by App\Services\Inbox\ReplyThreading, keyed on the
 * in_reply_to_email_id recorded below. That split is the point: `emails.body` is what the
 * AI reads, so storing the quote would re-send the entire thread to the model on every
 * reply — cost that grows with the thread and buys nothing, since the model is judging
 * the new text. The client still receives the full quoted chain.
 *
 * Block-built emails (composition_type = 'blocks') follow the same rule for the same
 * reason: `body` holds the rendered HTML with `cid:` image references, which cost a
 * handful of tokens and are stripped entirely by the workflow's remove_html transform.
 * The image bytes only ever travel inside the outgoing MIME message. See BlockComposition.
 */
class InboxReplyController extends Controller
{
    public function __construct(
        private readonly InboxAccess $access,
        private readonly BlockComposition $blocks,
        private readonly BlockRenderer $renderer,
        private readonly EmailImageStore $images,
        private readonly Correspondent $correspondent,
    ) {}

    /** POST /api/inbox/threads/{conversation}/reply */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        // emails.sender and project.clients: see the note in recipients().
        $conversation->load(['emails.sender', 'conversable', 'project.clients']);

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
            // template | custom | blocks.
            //   template — renders from an EmailTemplate plus template_data at send time
            //   custom   — free-form prose, stored verbatim in `body`
            //   blocks   — the "Project update" builder; block JSON is stored in
            //              template_data['blocks'] and rendered into `body` here
            'composition_type' => ['required', Rule::in(['template', 'custom', 'blocks'])],
            'subject' => ['required', 'string', 'max:255'],
            // A templated reply has no body of its own — the text comes from the template.
            // A block reply has no typed body either — it is rendered from `blocks`.
            'body' => ['required_if:composition_type,custom', 'nullable', 'string'],
            'body_format' => ['sometimes', 'nullable', 'string', Rule::in(['markdown', 'plain'])],
            'file_ids' => ['sometimes', 'array', 'max:20'],
            'file_ids.*' => ['integer'],
            'blocks' => ['required_if:composition_type,blocks', 'nullable', 'array', 'max:60'],
            'blocks.*.type' => ['required', Rule::in(['text', 'bullets', 'link', 'image'])],
            'blocks.*.text' => ['nullable', 'string', 'max:20000'],
            'blocks.*.label' => ['nullable', 'string', 'max:200'],
            'blocks.*.url' => ['nullable', 'string', 'max:2000'],
            'blocks.*.alt' => ['nullable', 'string', 'max:200'],
            'blocks.*.file_id' => ['nullable', 'integer'],
            'template_id' => ['required_if:composition_type,template', 'nullable', 'integer', 'exists:email_templates,id'],
            'template_data' => ['nullable', 'array'],
            /*
             * The opening line, already built — "Hi Sarah," — not a name to build one
             * from. Same shape and same key the classic composer posts to
             * POST /api/emails, so one composer produces one string and neither end has a
             * second opinion about how to address a client.
             *
             * Optional, and empty means none: a reply had no greeting at all before this,
             * an AI draft writes its own, and both of those must stay possible.
             */
            'greeting_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            /*
             * Keep this message out of the project team's view. Same flag the toggle next
             * to a sent message writes, set at compose time instead of after the fact.
             * Permission-checked below; absent or false is the normal case.
             */
            'is_private' => ['sometimes', 'boolean'],
            /*
             * Only honoured for `forward`, and only from someone with
             * `email_custom_recipients`. For reply and replyAll the server resolves the
             * recipients from the project and ignores this entirely — see
             * resolveRecipients().
             *
             * No `cc` or `bcc`. They were accepted before and quietly did nothing: the
             * emails table has no column for either, so the endpoint wrote the addresses
             * into a team note and sent to `to` only. A field that looks like it copies
             * somebody and does not is worse than no field, and under a client-only rule
             * there is nobody to copy anyway. Real Cc is a future change — a Cc column, a
             * Cc header, and one send instead of a loop.
             */
            'to' => ['nullable', 'array'],
            'to.*' => ['email'],
            /*
             * The chosen recipients, as KEYS from the recipients endpoint's `candidates`
             * — `client:42`, `sender:918` — never addresses. Resolved back through
             * Correspondent::selectableFor in resolveRecipients(), so a key naming
             * somebody who is not on this thread resolves to nothing rather than to a
             * recipient, and the "nothing the browser posted" property survives handing
             * the choice over.
             *
             * Optional: an older bundle posts none, and that still means everyone on the
             * thread — what it did before this existed.
             */
            'recipient_keys' => ['sometimes', 'array', 'max:50'],
            'recipient_keys.*' => ['string', 'max:64'],
            // The message being answered. Drives both the Gmail threading headers and the
            // quoted chain at send time; validated against this conversation below so a
            // reply cannot be linked to a message on someone else's thread.
            'in_reply_to_email_id' => ['nullable', 'integer', 'exists:emails,id'],
        ]);

        /*
         * Forwarding is the only way to send a client thread to an address that is not on
         * the project, so it carries the permission that governs exactly that. Being a
         * manager is no longer enough: managers reply to clients constantly, and that
         * needs no permission at all, but choosing a new recipient is a different act.
         * See InboxAccess::canAddressManually.
         */
        if ($data['mode'] === 'forward' && ! $this->access->canAddressManually($user)) {
            abort(403, 'Forwarding needs the "Email Custom Recipients" permission — client mail otherwise only goes to the project\'s clients.');
        }

        /*
         * Composer gating, enforced here rather than only hidden in the UI.
         *
         * The legacy page hides its Custom Email button from everyone but super admins and
         * then accepts a custom email from anyone who posts one — the endpoint never
         * checks. Reproducing the hole alongside the button would be a poor trade, so the
         * new reply endpoint enforces every gate. See InboxAccess::canComposeCustom for
         * why "custom" resolves to super-admin-only today, and canComposeBlocks for why
         * "blocks" deliberately does not.
         */
        $isTemplate = $data['composition_type'] === 'template';
        $isBlocks = $data['composition_type'] === 'blocks';

        if ($isTemplate && ! $this->access->canComposeTemplate($user)) {
            abort(403, 'You do not have permission to send template emails.');
        }

        /*
         * Blocks used to be gated as custom, on the reasoning that the builder is
         * free-form content wearing a nicer editor. It is not: it emits a fixed set of
         * typed blocks that BlockRenderer turns into our own markup, with no HTML
         * passthrough and no free-text recipient, so it puts the same class of thing in
         * front of a client that a template does. It now carries its own gate, which any
         * composer clears — see InboxAccess::canComposeBlocks.
         *
         * A genuinely free-form body is unchanged and still admin-only.
         */
        if ($isBlocks && ! $this->access->canComposeBlocks($user)) {
            abort(403, 'You do not have permission to send emails on this thread.');
        }

        if (! $isTemplate && ! $isBlocks && ! $this->access->canComposeCustom($user)) {
            abort(403, 'Free-form emails are admin-only — build your reply from a template or a project update.');
        }

        /*
         * Sending as private.
         *
         * Refused rather than silently downgraded: someone who ticked the box believes
         * this message is being kept from the team, and creating it visible anyway is the
         * one failure mode worth being loud about.
         */
        $isPrivate = (bool) ($data['is_private'] ?? false);

        if ($isPrivate && ! $this->access->canMarkPrivate($user)) {
            abort(403, 'Marking a message private needs the "Delete Emails" permission.');
        }

        /*
         * Templates need a project, and a lead conversation has none.
         *
         * HandlesTemplatedEmails::populateAllPlaceholders types its $project parameter as
         * non-nullable `Project`, so rendering a templated email on a project-less thread
         * is a TypeError — an Error, not an Exception, so editAndApprove's catch block
         * does not stop it and the send 500s. The read side survives only because
         * EmailBodyRenderer catches Throwable, which means nothing warns you until you
         * press Send. Refuse it up front instead.
         */
        if ($isTemplate && ! $conversation->project_id) {
            abort(422, 'Template emails need a project. This thread is a lead, so reply with a custom message.');
        }

        // Which projects this person may pull a block image from. Passed into sanitise()
        // so an image block naming a file id from a project they cannot compose on is
        // dropped rather than embedded — the ids are sequential and would otherwise be
        // trivially enumerable.
        $composable = $this->access->composableProjectIds($user);

        /*
         * Block images are uploaded against a project (files.fileable_id is NOT NULL and
         * there is no Email to hang them on while composing — see EmailImageStore). A
         * lead thread has no project, so an image block on one has nowhere to have come
         * from; text-only blocks are fine.
         */
        if ($isBlocks && ! $conversation->project_id
            && $this->blocks->imageIds($this->blocks->sanitise($data['blocks'] ?? [], $composable)) !== []) {
            abort(422, 'Images need a project. This thread is a lead, so send the update without them.');
        }

        /*
         * Draft, always — see the class docblock.
         *
         * This is the status workflow 17 selects on, so creating the row IS the
         * submission. There is no branch here for a parked draft because the schema has
         * no state that Email::created does not reach.
         */
        $status = EmailStatus::Draft;

        // Default to the newest inbound message on this thread — that is what a reply
        // answers. Anything explicitly passed must belong to THIS conversation; accepting
        // an arbitrary email id would quote another thread's messages into this one.
        $parentId = $data['in_reply_to_email_id'] ?? null;

        if ($parentId) {
            $parent = $conversation->emails->firstWhere('id', (int) $parentId);

            if (! $parent) {
                abort(422, 'That message is not part of this thread.');
            }

            // A reply can only be anchored to something the other party has actually
            // seen. An unsent or rejected draft has no Message-ID to thread onto and is
            // excluded from the quote, so accepting one would produce a reply whose
            // headers and quoted history both point somewhere other than the message the
            // UI said was being answered.
            $parentType = $parent->type instanceof EmailType
                ? $parent->type->value
                : (string) $parent->type;
            $parentStatus = $parent->status instanceof EmailStatus
                ? $parent->status->value
                : (string) $parent->status;

            $seenByThem = $parentType === EmailType::Received->value
                || $parentStatus === EmailStatus::Sent->value;

            if (! $seenByThem) {
                abort(422, 'You can only reply to a message that has actually been sent or received.');
            }
        }

        if (! $parentId) {
            $parentId = $conversation->emails
                ->sortBy(fn (Email $e) => ($e->sent_at ?? $e->created_at)?->timestamp ?? 0)
                ->last(fn (Email $e) => ($e->type instanceof EmailType ? $e->type->value : (string) $e->type)
                    === EmailType::Received->value)?->id;
        }

        $to = $this->resolveRecipients(
            $conversation,
            $data['mode'],
            $data['to'] ?? [],
            $user,
            $data['recipient_keys'] ?? null,
            // The message being answered, so the candidate list is built against the same
            // reply target the composer showed. `$parentId` is already resolved above,
            // including the fallback to the newest inbound message.
            $parentId ? $conversation->emails->firstWhere('id', $parentId) : null,
        );

        if (empty($to)) {
            // An empty selection is a different problem from a thread with nobody on it,
            // and telling someone their project has no clients when they simply unticked
            // everyone sends them looking in the wrong place.
            if (($data['recipient_keys'] ?? null) !== null && $data['mode'] !== 'forward') {
                abort(422, 'Choose at least one person to send this to.');
            }

            abort(422, $data['mode'] === 'forward'
                ? 'Enter at least one valid address to forward this to.'
                : match ($this->correspondent->kindFor($conversation)) {
                    'lead' => 'This lead has no email address on record.',
                    'client' => 'No client with an email address is attached to this thread.',
                    default => 'We could not work out who to send this to.',
                });
        }

        /*
         * Blocks are normalised and rendered before the row is written.
         *
         * sanitise() drops anything the builder should not have sent — an unknown block
         * type, an image whose file_id is not one of our own `email-blocks/` uploads — so
         * a hand-rolled POST cannot embed an arbitrary attachment into a client email.
         * The render is MODE_SEND, i.e. `cid:` references, because that string is both
         * what Gmail receives and what the AI reads.
         */
        $blocks = $isBlocks ? $this->blocks->sanitise($data['blocks'] ?? [], $composable) : [];

        if ($isBlocks && $blocks === []) {
            abort(422, 'There is nothing in this update yet — add a block before sending it.');
        }

        $renderedBlocks = $isBlocks
            ? $this->renderer->render($blocks, BlockRenderer::MODE_SEND)
            : null;

        /*
         * The greeting, prepended to a CUSTOM body only.
         *
         * `$greeting.'<br/>'.$body` is deliberately byte-identical to what
         * HandlesEmailCreation does for a new email, so a reply and a first message open
         * the same way and EmailHtml has one shape to render.
         *
         * Not for a template, which renders its own opening from the template; not for
         * blocks, where a greeting has to BE a block or the send-time re-render drops it
         * (see BlockComposition::renderForSend).
         *
         * Null when the composer sent nothing or sent an empty string — which is what an
         * AI draft does, because InboxAiService::draftReply is prompted to open with "Hi
         * <first name>," itself. Prepending on top of that is how you get two greetings.
         */
        $greeting = $isTemplate || $isBlocks
            ? null
            : (trim((string) ($data['greeting_name'] ?? '')) ?: null);

        $bodyIsMarkdown = ! $isTemplate && ! $isBlocks && (($data['body_format'] ?? null) === 'markdown' || \App\Services\Inbox\MarkdownBody::looksLikeMarkdown($data['body'] ?? ''));

        $email = new Email([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => \App\Models\User::class,
            'to' => $to,
            'subject' => $data['subject'],
            // Null for a templated reply: the text is rendered from the template on read
            // and on send, and a stale copy here would be the one thing nobody updates.
            // A block reply DOES store its rendered HTML, because the blocks are the
            // source of truth and they live on the same row — nothing can drift.
            'body' => match (true) {
                $isTemplate => null,
                $isBlocks => $renderedBlocks,
                default => $greeting === null
                    ? $data['body']
                    : $greeting.'<br/>'.$data['body'],
            },
            'status' => $status->value,
            'type' => EmailType::Sent->value,
            'in_reply_to_email_id' => $parentId,
            // Set at creation, so the message is never briefly visible to the team between
            // being written and someone remembering to flip the toggle. Sending is
            // unaffected — the client receives it either way; this only governs who on our
            // side can read it afterwards.
            'is_private' => $isPrivate,
            // Written through TemplateData::encode, which reproduces the double-encoded
            // shape every other writer in the codebase produces. Storing the "correct"
            // single-encoded array instead would 500 the classic pending-approvals list
            // for everyone — three legacy readers still call json_decode() on this value
            // directly, and json_decode(array) is a TypeError their catch blocks miss.
            // See App\Support\TemplateData.
            'template_id' => $isTemplate ? $data['template_id'] : null,
            'template_data' => match (true) {
                $isTemplate => \App\Support\TemplateData::encode($data['template_data'] ?? []),
                // A block email has no template, but it borrows the column: the builder's
                // JSON goes under `blocks` so the send path can rebuild the CID map and
                // the composer can be reopened. template_id stays null, so every existing
                // reader that keys off template_id ignores this row exactly as before.
                $isBlocks => \App\Support\TemplateData::encode([BlockComposition::KEY => $blocks]),
                default => null,
            },
            'draft_meta' => $bodyIsMarkdown ? ['body_format' => 'markdown'] : null,
        ]);
        $email->save();

        // Re-point the images from the project to the email now that there is one. Only
        // moves rows still owned by a project and under our own prefix, so a stale or
        // hostile id cannot steal someone else's file. Anything left behind is swept by
        // files:prune-expired when its TTL runs out.
        if ($isBlocks) {
            $this->images->attachTo($email, $this->blocks->imageIds($blocks));
        }

        // Attach any uploaded files/images
        if (! empty($data['file_ids'])) {
            app(\App\Services\Inbox\EmailAttachmentStore::class)->attachTo($email, $data['file_ids']);
        }

        $conversation->forceFill(['last_activity_at' => now()])->save();

        /*
         * No CheckEmailWithAi dispatch here, deliberately.
         *
         * The automation workflow already runs an AI approval analysis on every draft it
         * picks up, and it is the one whose verdict actually decides whether the email
         * goes out. Queueing our own checker alongside it would bill a second model call
         * per reply to produce an advisory flag that changes nothing. The redesign's
         * checker stays for the screening of INBOUND mail and for the manual "check this
         * again" action; `inbox.ai.check_outbound` is left in config but is now the
         * belt-and-braces option rather than the mechanism. See CheckEmailWithAi.
         */

        return response()->json([
            'data' => [
                'email_id' => $email->id,
                'status' => $status->value,
                'conversation_id' => $conversation->id,
                'composition_type' => $data['composition_type'],
                // Always true now: the row has been created, so the automation owns it.
                // The client shows "submitted for review", never "sent" — nobody here
                // knows yet whether the AI will pass it or hand it to a human.
                'submitted' => true,
                // Deliberately false. The old two-step (create at pending_approval, then
                // POST edit-and-approve) is gone; see the class docblock. Kept in the
                // payload so a stale bundle that still checks it takes the safe branch
                // rather than firing a request that would now 400.
                'can_send' => false,
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
        // emails.sender and project.clients: Correspondent walks both when the
        // conversable is missing, and without eager loading that is a query per email.
        $conversation->load(['emails.sender', 'conversable', 'project.clients']);

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

        $to = $this->resolveRecipients($conversation, 'reply', [], $user);

        /*
         * Who this reply MAY go to, as a list to pick from — and which of them is ticked
         * by default. The composer used to be handed a finished address list and no say in
         * it; see Correspondent::selectableFor for why that was wrong and how the keys keep
         * the choice server-verifiable.
         */
        $candidates = collect($this->correspondent->selectableFor($conversation, $lastInbound))
            ->map(fn (array $c) => [
                'key' => $c['key'],
                'name' => $c['name'],
                // Masked for anyone without edit_clients, exactly like `reply` below. The
                // key is what the composer posts, so a masked address is only ever a label.
                'address' => $mask ? $this->maskAddress($c['address']) : $c['address'],
                'suggested' => $c['suggested'],
                'reason' => $c['reason'],
            ])
            ->values();

        return response()->json([
            // reply and replyAll are the same list now — the recipients are the project's
            // clients either way. Both keys are still returned so an older bundle asking
            // for replyAll gets the right addresses rather than an empty box.
            'reply' => $show($to),
            'replyAll' => $show($to),
            'forward' => [],
            'subject' => $this->replySubject($conversation),
            'last_inbound_email_id' => $lastInbound?->id,
            'masked' => $mask,

            // Who to say hello to. NAMES, never addresses — the composer builds the
            // greeting line from these, and they are the same names the thread already
            // prints on every message, so nothing is exposed that was not already on
            // screen. See Correspondent::namesFor.
            'recipient_names' => $this->correspondent->namesFor($conversation),

            // The picker's options, and its initial state.
            'candidates' => $candidates,
            'suggested_keys' => $candidates->where('suggested', true)->pluck('key')->values(),

            // Whether this person may type an address at all. The composer hides the
            // forward option and the address field unless this is true; the server refuses
            // regardless, so this only decides what is worth drawing.
            'can_address_manually' => $this->access->canAddressManually($user),

            // Why the box is empty, when it is — so the composer can say "this thread has
            // no client attached" instead of showing a blank field and a 422 on send.
            // Why the box is empty, in the language of whatever kind of thread this is.
            // A lead thread with no address is a different problem from a project with no
            // clients, and telling someone their lead thread "is not attached to a
            // project" sends them looking in the wrong place.
            'reason' => $to === [] ? match ($this->correspondent->kindFor($conversation)) {
                'lead' => 'This lead has no email address on record, so there is nobody to reply to.',
                'client' => $conversation->project_id
                    ? 'No client with an email address is attached to this project.'
                    : 'This thread has no client with an email address on record.',
                default => 'We could not work out who sent this, so there is no address to reply to. Forward it instead, or attach the sender to a project.',
            } : null,

            // Client or lead, so the composer can say the right thing.
            'kind' => $this->correspondent->kindFor($conversation),
        ]);
    }

    /**
     * Who a reply actually goes to: the clients on the project. Nothing else.
     *
     * ## The rule
     *
     * Client mail travels one route — from our authorised mailbox, signed with our
     * details, to the clients attached to the project. That is what the Gmail integration
     * is FOR: a client can answer us without being able to reach an individual staff
     * member directly, and only a handful of people have access to the mailbox itself. So
     * the recipients are derived here, from the project, and whatever the client posted is
     * discarded. There is no request shape that can redirect a client thread.
     *
     * ## Why the project, and not the conversation's conversable
     *
     * The old rule read `$conversation->conversable` and stopped. That is null on every
     * thread `EmailReceiveController::handleUnknownEmail` creates — it keys those on
     * subject alone with no conversable and no project — so pressing Reply on one of them
     * produced an empty To box and a 422 on send. Reading the project's client list
     * instead fixes that for every thread that has a project, however the conversation was
     * created, and picks up clients added to the project after the thread started.
     *
     * The conversable still goes FIRST when it is a client, because that is the person who
     * actually wrote in, and it is kept even if they are no longer on the project — losing
     * the one person you are answering would be a worse failure than emailing one extra.
     *
     * ## Multiple clients
     *
     * All of them go in `to`. The send loop transmits one message per address, so each
     * client receives their own copy and does not see the others — which is more private
     * than a Cc, not less. A real Cc (one message, a Cc header, everyone visible to
     * everyone) needs a Cc column on `emails` and a change to the send loop, and is
     * deliberately left for later.
     *
     * @param  array<string>  $requested  only consulted for `forward`, and only with permission
     * @return array<string>
     */
    private function resolveRecipients(
        Conversation $conversation,
        string $mode,
        array $requested = [],
        mixed $user = null,
        ?array $keys = null,
        ?Email $replyingTo = null
    ): array {
        if ($mode === 'forward') {
            // Gated by the caller too; re-checked here so no future path can reach this
            // method and get an arbitrary address honoured.
            if (! $user || ! $this->access->canAddressManually($user)) {
                return [];
            }

            return array_values(array_unique(array_filter(
                array_map('trim', $requested),
                fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)
            )));
        }

        /*
         * A chosen subset, when the composer sent one.
         *
         * The keys are resolved against selectableFor() — the same method that produced
         * the list the person picked from — so the only addresses reachable here are the
         * ones already on this thread. A key for somebody else's client resolves to
         * nothing; it is not an error, it simply is not a recipient.
         *
         * `reply` and `replyAll` are the same code path. The two modes stopped differing
         * when recipients became a client-only rule, and rather than reviving the
         * distinction the composer now offers one Reply and lets you tick who it goes to.
         * `replyAll` is still accepted so an older bundle keeps working.
         */
        if ($keys !== null) {
            $wanted = array_flip(array_map('strval', $keys));

            $chosen = array_values(array_filter(array_map(
                fn (array $c) => isset($wanted[$c['key']]) ? $c['address'] : null,
                $this->correspondent->selectableFor($conversation, $replyingTo)
            )));

            /*
             * An empty result is NOT silently widened back to everyone. If the person
             * unticked every recipient, sending to the whole project instead is the worst
             * possible reading of that. store() turns this into a 422.
             */
            return $chosen;
        }

        // No selection posted — an older bundle. Everyone on the thread, as before.
        //
        // Correspondent owns the chain — conversable, then project clients, then the From
        // header of the newest inbound message, then whoever we last wrote to. That third
        // step is what makes Reply work on a lead thread and on the project-less threads
        // handleUnknownEmail creates, both of which used to produce an empty box.
        return $this->correspondent->addressesFor($conversation);
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
