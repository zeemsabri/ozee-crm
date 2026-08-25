<?php

namespace App\Services\Inbox;

use App\Enums\EmailStatus;
use App\Models\Email;
use App\Services\GmailService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Finding, and binning, an email's copy in Gmail.
 *
 * ## The problem this exists for
 *
 * Deleting the Gmail copy needs `emails.message_id` — Gmail's **API** id. That column is
 * populated inconsistently, and not because anything is broken:
 *
 *  - **Inbound** mail has it. `EmailReceiveController` writes it as it polls.
 *  - **Outbound** mail did not get it at send time — not because Gmail withholds it, but
 *    because we threw it away. `GmailService::sendMessage` has always returned
 *    `['id' => …, 'threadId' => …]`, and both send paths kept only `threadId`. Fixed at
 *    the source 2026-08-25; every send since records the id immediately.
 *  - `IngestSentMail` also back-fills it, by walking SENT and matching our minted
 *    Message-ID header. That is capped per run and, as below, the header match itself is
 *    unreliable for our own mail — which is why so many sent rows had no id.
 *
 * ## Two ways to find a missing id, and why the obvious one is not enough
 *
 * `rfc822msgid:` is a first-class Gmail search operator, so one `messages.list` call turns
 * a Message-ID header into an API id. That works for INBOUND mail, whose header we read
 * off the real message.
 *
 * It frequently finds NOTHING for our own outbound mail. We mint a Message-ID and set it
 * on the raw message, but Gmail may replace it on send — in which case our column names a
 * message that exists nowhere. A backfill run over 50 such rows resolved 0 of them.
 *
 * So there is a second route: `gmail_thread_id`, which IS recorded at send time and is
 * reliable. Walk the thread, find our message in it. See byThread() for the matching rule
 * and the one-hour bound on it.
 *
 * Trashing by THREAD is still never done — that would bin the client's messages in the
 * conversation too, which is not what "delete the Gmail copy" of one message means. The
 * thread is used to FIND the message, not to act on.
 */
class GmailCopy
{
    public function __construct(private readonly GmailService $gmail) {}

    /**
     * Gmail's API id for this email, looked up and persisted if we only had the header.
     *
     * Two strategies, in order, because the first one does not work for our OWN sent mail
     * as often as you would expect — see byHeader().
     *
     * `$via` reports which one answered ('column', 'header', 'thread'), for the backfill
     * command's report. Returns null when nothing can find it.
     */
    public function idFor(Email $email, ?string &$via = null): ?string
    {
        if ($email->message_id) {
            $via = 'column';

            return $email->message_id;
        }

        if ($id = $this->byHeader($email)) {
            $via = 'header';
            $this->remember($email, $id);

            return $id;
        }

        if ($id = $this->byThread($email)) {
            $via = 'thread';
            $this->remember($email, $id);

            return $id;
        }

        $via = null;

        return null;
    }

    /**
     * Find it by the Message-ID header we stored.
     *
     * Works reliably for INBOUND mail, whose `rfc_message_id` was read off a header Gmail
     * itself holds. It is much less reliable for our own outbound mail: we mint a
     * Message-ID and set it on the raw message, but Gmail may replace it on send with one
     * of its own, in which case the value in our column names a message that does not
     * exist anywhere and this search correctly finds nothing.
     *
     * That is why byThread() exists, and why the send paths now record the id Gmail
     * returns instead of relying on this at all.
     */
    private function byHeader(Email $email): ?string
    {
        $header = trim((string) $email->rfc_message_id);

        if ($header === '') {
            return null;
        }

        /*
         * The column stores the header as it appears on the wire, angle brackets included
         * — `<uuid.42@ozeeweb.com.au>` from newMessageId(), and whatever the sender's
         * client wrote for inbound mail. Gmail's operator wants the addr-spec WITHOUT
         * them, and quietly returns nothing rather than erroring if you leave them on,
         * which is exactly the kind of failure that reads as "the message isn't there".
         */
        $addrSpec = trim($header, '<> ');

        if ($addrSpec === '') {
            return null;
        }

        try {
            // One result is all there can be: a Message-ID is unique per message.
            $ids = $this->gmail->listMessages(1, 'rfc822msgid:'.$addrSpec);
        } catch (\Throwable $e) {
            Log::warning('inbox.gmail: rfc822msgid lookup failed', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $ids[0] ?? null;
    }

    /**
     * Find it by walking the Gmail thread we recorded.
     *
     * `gmail_thread_id` IS written at send time and is reliable, so for an outbound email
     * whose minted Message-ID Gmail discarded, the thread is the way back to the message.
     *
     * Matching, in order of confidence:
     *  1. A message in the thread whose Message-ID header equals ours. Free, and settles
     *     it when Gmail did keep our header.
     *  2. Failing that, the message from OUR authorised address whose internalDate is
     *     closest to when we recorded the send.
     *
     * Step 2 is a judgement, so it is bounded: more than an hour away from the recorded
     * send time and it is refused rather than guessed. Binning the wrong message out of a
     * client thread is far worse than declining to bin anything.
     *
     * NOTE the multi-recipient case. sendMessage() is called once per recipient, so an
     * email to three clients is three Gmail messages against one row, and one column can
     * hold one id — the first. Deleting the Gmail copy therefore bins our copy of the
     * first recipient's message. Widening that needs a message_id-per-recipient table, not
     * a cleverer lookup here.
     */
    private function byThread(Email $email): ?string
    {
        $threadId = trim((string) $email->gmail_thread_id);

        if ($threadId === '') {
            return null;
        }

        try {
            $messages = $this->gmail->getThread($threadId);
        } catch (\Throwable $e) {
            Log::warning('inbox.gmail: thread lookup failed', [
                'email_id' => $email->id,
                'thread_id' => $threadId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($messages === []) {
            return null;
        }

        $ourHeader = trim((string) $email->rfc_message_id, '<> ');

        if ($ourHeader !== '') {
            foreach ($messages as $message) {
                if (trim((string) $message['messageIdHeader'], '<> ') === $ourHeader) {
                    return $message['id'];
                }
            }
        }

        $sentAt = $email->sent_at ?? $email->created_at;

        if (! $sentAt) {
            return null;
        }

        try {
            $us = strtolower($this->gmail->getAuthorizedEmail());
        } catch (\Throwable) {
            // Declared `: string` but reads a property that is null until the client has
            // authorised — the same trap EmailProcessingService rescues around.
            return null;
        }

        $target = $sentAt->getTimestamp() * 1000;
        $best = null;
        $bestGap = null;

        foreach ($messages as $message) {
            if ($us === '' || ! str_contains(strtolower($message['from'] ?? ''), $us)) {
                continue;
            }

            if (! $message['internalDate']) {
                continue;
            }

            $gap = abs($message['internalDate'] - $target);

            if ($bestGap === null || $gap < $bestGap) {
                $best = $message['id'];
                $bestGap = $gap;
            }
        }

        // One hour. Generous enough for clock skew and a queue that ran late, tight enough
        // that a different message we sent on the same thread cannot be mistaken for this
        // one.
        return $bestGap !== null && $bestGap <= 3600_000 ? $best : null;
    }

    /**
     * Write the id back, so this costs one lookup per email ever rather than one per
     * delete.
     *
     * saveQuietly: `email.updated` is what the automation workflow listens to, and
     * learning a Gmail id is not a change to the email.
     */
    private function remember(Email $email, string $id): void
    {
        try {
            $email->forceFill(['message_id' => $id])->saveQuietly();
        } catch (UniqueConstraintViolationException) {
            Log::warning('inbox.gmail: message_id already taken by another email', [
                'email_id' => $email->id,
                'message_id' => $id,
            ]);
        }
    }

    /**
     * Bin this email's Gmail copy.
     *
     * @return true|string|null true when trashed; a human-readable reason when it could
     *                          not be; null when there is nothing to trash and that is not
     *                          a failure.
     */
    public function trash(Email $email): true|string|null
    {
        $id = $this->idFor($email);

        if ($id) {
            try {
                $this->gmail->trashMessage($id);

                return true;
            } catch (\Throwable $e) {
                Log::error('inbox.gmail: could not trash message', [
                    'email_id' => $email->id,
                    'gmail_id' => $id,
                    'error' => $e->getMessage(),
                ]);

                return 'Gmail refused to delete one message: '.$e->getMessage();
            }
        }

        /*
         * No id, and none findable. Whether that is worth saying depends on whether this
         * message was ever delivered: a draft or a rejected draft has no Gmail copy and
         * reporting one would be noise, while a SENT message Gmail cannot find is worth
         * naming — it usually means the copy is already in the bin, or the message went
         * out before we started stamping Message-IDs and cannot be matched at all.
         */
        $status = $email->status instanceof EmailStatus
            ? $email->status->value
            : (string) $email->status;

        $delivered = in_array($status, [
            EmailStatus::Sent->value,
            EmailStatus::Approved->value,
        ], true);

        if (! $delivered) {
            return null;
        }

        return $email->rfc_message_id || $email->gmail_thread_id
            ? 'One sent message could not be matched to anything in Gmail, so nothing was deleted there. It is probably already in the Gmail bin.'
            : 'One sent message predates Gmail id tracking, so its Gmail copy could not be matched and was left alone.';
    }
}
