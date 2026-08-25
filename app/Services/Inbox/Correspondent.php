<?php

namespace App\Services\Inbox;

use App\Enums\EmailType;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Lead;
use App\Support\TemplateData;
use Illuminate\Support\Collection;

/**
 * Who a thread is with: what to call them, and where to write to them.
 *
 * One class for both questions because they kept disagreeing. The reply box resolved
 * recipients from `conversation->conversable`, the thread header printed
 * `conversable?->name`, and the per-message line printed the raw `emails.to` array — three
 * answers to "who is this person", each wrong in a different situation.
 *
 * ## Names, never addresses
 *
 * The timeline used to render "Priya Nair to priya@acme.com". It must not: the whole point
 * of routing client mail through our mailbox is that individual staff do not hold, and are
 * not casually shown, client contact details — `Client` even hides `email` from anyone
 * without `edit_clients`, which the timeline was quietly working around by reading
 * `emails.to` directly. So every label here is a NAME. A message reads "Priya Nair to the
 * OZee team" inbound, or "Sam Ito to Priya Nair" outbound.
 *
 * ## Leads are people too
 *
 * `Lead` has `first_name` and `last_name`, not `name`. Every caller that did
 * `$conversable?->name` therefore got null on a lead thread and fell through to "Unknown
 * sender" or an empty recipient box — even though the lead's address was sitting right
 * there. nameFor() knows the difference between the two models so callers do not have to.
 */
class Correspondent
{
    /** What we call ourselves when we are the recipient. */
    public function teamLabel(): string
    {
        return (string) config('inbox.team_label', 'OZee Team');
    }

    /**
     * A person's display name, whatever kind of record they are.
     *
     * Client and User carry `name`. Lead carries `first_name`/`last_name` and often a
     * `company` when the contact name is blank. Returns null rather than falling back to
     * an email address — an address is exactly what must not appear in the UI.
     */
    public function nameFor(mixed $model): ?string
    {
        if (! $model) {
            return null;
        }

        if ($model instanceof Lead) {
            $name = trim(implode(' ', array_filter([
                $model->first_name ?? null,
                $model->last_name ?? null,
            ])));

            return $name !== '' ? $name : ($model->company ?: null);
        }

        $name = trim((string) ($model->name ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * A person's real address, past the Client model's permission-based hiding.
     *
     * `Client` adds `email` to `$hidden` in a `retrieved` hook for anyone without
     * `edit_clients`, so `$client->email` is null for most staff. getRawOriginal() reads
     * the underlying attribute, which is what actually addresses the message. This is
     * server-side only — the address never reaches the browser for those users.
     */
    public function addressFor(mixed $model): ?string
    {
        if (! $model) {
            return null;
        }

        $raw = method_exists($model, 'getRawOriginal') ? $model->getRawOriginal('email') : null;
        $address = $raw ?: ($model->email ?? null);

        return is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : null;
    }

    /**
     * Who this thread is with, as a name.
     *
     * Falls back through the same chain as the addresses below, so the header and the
     * reply box never disagree about who you are talking to.
     */
    public function labelFor(Conversation $conversation, ?Collection $emails = null): string
    {
        if ($name = $this->nameFor($conversation->conversable)) {
            return $name;
        }

        // A project thread with no conversable: name the project's client.
        if ($conversation->project) {
            $client = $conversation->project->clients->first();

            if ($name = $this->nameFor($client)) {
                return $name;
            }
        }

        // Whoever last wrote in. `sender` is set when the poller matched them to a record.
        $inbound = ($emails ?? $conversation->emails ?? collect())
            ->last(fn (Email $e) => $this->isInbound($e));

        if ($inbound && $name = $this->nameFor($inbound->sender)) {
            return $name;
        }

        // Deliberately not the address. An unmatched sender is anonymous in the UI; the
        // reply still reaches them because addressesFor() can read the stored header.
        return $inbound ? 'Client' : 'Unknown sender';
    }

    /**
     * Every address a reply on this thread may go to, best first.
     *
     * Four sources, tried in order, because no single one covers every way a conversation
     * gets created in this system:
     *
     *  1. The conversable — a Client on a project thread, a Lead on an outreach thread.
     *     This is the person the thread is with, and they come first even if they have
     *     since been detached from the project: losing the one person you are answering
     *     would be worse than emailing one extra.
     *
     *  2. The project's clients. This is what makes Reply work on a thread whose
     *     conversable was never set, and what keeps a client added to the project later in
     *     the loop.
     *
     *  3. The `From` header of the newest inbound message. `handleUnknownEmail` creates
     *     conversations keyed on subject alone — no project, no conversable — for every
     *     sender the poller could not match to a Client or Lead. Those threads have a real
     *     person on the other end and previously produced an empty reply box. The address
     *     is recoverable because the poller stores the raw headers in `template_data`.
     *
     *  4. The `to` of the newest outbound message, for a thread we started and whose
     *     conversable has since gone.
     *
     * Note what is NOT a source: anything the browser posted. Recipients are derived
     * server-side so a client thread cannot be redirected by editing a request.
     *
     * @return array<string>
     */
    public function addressesFor(Conversation $conversation, ?Collection $emails = null): array
    {
        $emails = $emails ?? $conversation->emails ?? collect();
        $addresses = [];

        // 1. The conversable.
        $addresses[] = $this->addressFor($conversation->conversable);

        // 2. The project's clients.
        if ($conversation->project) {
            foreach ($conversation->project->clients as $client) {
                $addresses[] = $this->addressFor($client);
            }
        }

        // 3. Whoever last wrote to us.
        if ($inbound = $emails->last(fn (Email $e) => $this->isInbound($e))) {
            $addresses[] = $this->addressFor($inbound->sender);
            $addresses[] = $this->inboundFrom($inbound);
        }

        // 4. Whoever we last wrote to.
        if ($outbound = $emails->last(fn (Email $e) => ! $this->isInbound($e))) {
            foreach ((array) ($outbound->to ?? []) as $address) {
                $addresses[] = is_string($address) ? $address : null;
            }
        }

        return $this->clean($addresses);
    }

    /**
     * Who a reply on this thread MAY be addressed to, as a list to pick from.
     *
     * The reply box used to send to `addressesFor()` wholesale — every client on the
     * project, whether or not they had anything to do with the message being answered. On
     * a project with five contacts that meant answering one person's question in front of
     * four others, every time, with no way to narrow it.
     *
     * This returns candidates instead, each with a `key` the server can verify. That is
     * the point of the key format: the browser never posts an ADDRESS for a reply, it
     * posts `client:42`, and store() resolves that back through this same method. So a
     * person can choose among the people on this thread and cannot invent a recipient —
     * the property `addressesFor`'s "nothing the browser posted" note protects, kept while
     * handing over the choice.
     *
     * `suggested` marks the default selection: whoever sent the message being answered.
     * Everyone else on the project stays in the list, unticked, one click away.
     *
     * @param  Email|null  $replyingTo  the message being answered; the newest inbound one
     *                                  when the composer at the bottom is used
     * @return array<int,array{key:string,name:string,address:string,suggested:bool,reason:string}>
     */
    public function selectableFor(
        Conversation $conversation,
        ?Email $replyingTo = null,
        ?Collection $emails = null
    ): array {
        $emails = $emails ?? $conversation->emails ?? collect();
        $candidates = [];

        $add = function (string $key, ?string $name, ?string $address, string $reason) use (&$candidates) {
            if (! $address || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            // First writer wins, so the richer entry — a Client with a real name — is not
            // overwritten by a bare header for the same person.
            $candidates[mb_strtolower($address)] ??= [
                'key' => $key,
                'name' => $name ?: $address,
                'address' => $address,
                'suggested' => false,
                'reason' => $reason,
            ];
        };

        // The conversable first: the person the thread is with.
        if ($conversation->conversable) {
            $model = $conversation->conversable;
            $prefix = $model instanceof Lead ? 'lead' : 'client';
            $add(
                $prefix.':'.$model->getKey(),
                $this->nameFor($model),
                $this->addressFor($model),
                'on this thread'
            );
        }

        // Everyone else on the project — the people you may want to add.
        if ($conversation->project) {
            foreach ($conversation->project->clients as $client) {
                $add(
                    'client:'.$client->getKey(),
                    $this->nameFor($client),
                    $this->addressFor($client),
                    'on this project'
                );
            }
        }

        /*
         * The sender of the message being answered, and of the newest inbound message.
         *
         * Keyed by EMAIL id rather than by address, so an unmatched sender — someone the
         * poller could not tie to a Client record — is still selectable and still
         * server-verifiable: store() re-reads the header off that same row rather than
         * trusting anything posted.
         */
        $target = $replyingTo && $this->isInbound($replyingTo)
            ? $replyingTo
            : $emails->last(fn (Email $e) => $this->isInbound($e));

        if ($target) {
            $add(
                'client:'.($target->sender?->getKey() ?? 0),
                $this->nameFor($target->sender),
                $this->addressFor($target->sender),
                'wrote this message'
            );

            $add(
                'sender:'.$target->getKey(),
                $this->nameFor($target->sender),
                $this->inboundFrom($target),
                'wrote this message'
            );
        }

        $candidates = $this->withoutOurs($candidates);

        /*
         * The default selection.
         *
         * Whoever sent the message being answered, and nobody else. When there is no
         * inbound message to answer — a thread we started, or one whose sender has no
         * usable address — fall back to the first candidate, because a composer that opens
         * with nothing selected and no explanation is worse than one that opens with the
         * obvious person.
         */
        $suggested = [];

        if ($target) {
            foreach ([$this->addressFor($target->sender), $this->inboundFrom($target)] as $address) {
                if ($address && isset($candidates[mb_strtolower($address)])) {
                    $suggested[] = mb_strtolower($address);
                }
            }
        }

        if ($suggested === [] && $candidates !== []) {
            $suggested[] = array_key_first($candidates);
        }

        foreach ($suggested as $address) {
            $candidates[$address]['suggested'] = true;
            // Overwrite the reason: first-writer-wins above means a sender who is also on
            // the project reads "on this project", which is true but not why they are
            // ticked. The reason is shown next to the chip, so it should answer that.
            if ($target) {
                $candidates[$address]['reason'] = 'wrote this message';
            }
        }

        return array_values($candidates);
    }

    /**
     * Drop our own mailboxes from a keyed candidate list.
     *
     * Same rule as clean(), for the same reason: inbound mail stores `to` as the
     * authorised Gmail account, so without this a reply-all loops our own mail back into
     * the poller.
     */
    private function withoutOurs(array $candidates): array
    {
        $ours = array_map('mb_strtolower', array_filter([
            rescue(fn () => app(\App\Services\GmailService::class)->getAuthorizedEmail(), null, false),
            config('mail.from.address'),
            env('GOOGLE_PRIMARY_EMAIL'),
        ]));

        return array_filter(
            $candidates,
            fn ($address) => ! in_array($address, $ours, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Everyone on this thread we might greet, by name.
     *
     * The same chain as addressesFor(), minus the two steps that can only ever yield an
     * address: an unmatched inbound `From` header and a stored `emails.to`. Those are fine
     * for delivery and useless for a greeting — "Hi priya@acme.com," is worse than no
     * greeting at all — so they are left out rather than reformatted.
     *
     * Names, not addresses, so this is safe to return to someone without `edit_clients`:
     * the thread already prints these names on every message.
     *
     * @return array<int,string>
     */
    public function namesFor(Conversation $conversation, ?Collection $emails = null): array
    {
        $emails = $emails ?? $conversation->emails ?? collect();
        $names = [$this->nameFor($conversation->conversable)];

        if ($conversation->project) {
            foreach ($conversation->project->clients as $client) {
                $names[] = $this->nameFor($client);
            }
        }

        // Whoever last wrote in, when the poller matched them to a record. An unmatched
        // sender has no name to use and is deliberately skipped.
        if ($inbound = $emails->last(fn (Email $e) => $this->isInbound($e))) {
            $names[] = $this->nameFor($inbound->sender);
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($n) => is_string($n) ? trim($n) : null, $names),
            fn ($n) => is_string($n) && $n !== ''
        )));
    }

    /**
     * The sender's address off an inbound email, dug out of `template_data`.
     *
     * The poller stores the raw headers there, but in two different shapes depending on
     * which branch created the row: `handleUnknownEmail` writes
     * `json_encode(['from' => …, 'to' => …, …])`, while `handleClientEmailWithProject`
     * writes `strip_tags($body)` — a plain string with no headers in it at all. Both are
     * handled: TemplateData::decode returns an empty array for the second, and the
     * `from` key is simply absent.
     *
     * The value is a raw header like `"Priya Nair" <priya@acme.com>`, so the address is
     * extracted rather than used as-is.
     */
    private function inboundFrom(Email $email): ?string
    {
        $from = TemplateData::decode($email->template_data)['from'] ?? null;

        if (! is_string($from) || $from === '') {
            return null;
        }

        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            $from = $matches[1];
        }

        $from = trim($from);

        return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : null;
    }

    /**
     * Dedupe, drop blanks, and remove our own mailboxes.
     *
     * Inbound mail is stored with `to` set to the authorised Gmail account, so without the
     * last step a reply would be addressed to ourselves — it lands back in the polled
     * inbox, is re-ingested as a new client message, and the thread answers itself
     * forever.
     *
     * @param  array<string|null>  $addresses
     * @return array<string>
     */
    private function clean(array $addresses): array
    {
        $addresses = array_values(array_unique(array_filter(
            $addresses,
            fn ($a) => is_string($a) && filter_var($a, FILTER_VALIDATE_EMAIL)
        )));

        $ours = array_map('mb_strtolower', array_filter([
            rescue(fn () => app(\App\Services\GmailService::class)->getAuthorizedEmail(), null, false),
            config('mail.from.address'),
            env('GOOGLE_PRIMARY_EMAIL'),
        ]));

        return array_values(array_filter(
            $addresses,
            fn ($a) => ! in_array(mb_strtolower($a), $ours, true)
        ));
    }

    /** Which kind of record the thread is with, for the UI to label it. */
    public function kindFor(Conversation $conversation): string
    {
        return match (true) {
            $conversation->conversable instanceof Lead => 'lead',
            $conversation->conversable instanceof Client => 'client',
            $conversation->project_id !== null => 'client',
            default => 'unknown',
        };
    }

    private function isInbound(Email $email): bool
    {
        $type = $email->type instanceof EmailType ? $email->type->value : (string) $email->type;

        return $type === EmailType::Received->value;
    }
}
