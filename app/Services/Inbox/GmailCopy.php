<?php

namespace App\Services\Inbox;

use App\Enums\EmailStatus;
use App\Models\Email;
use App\Services\GmailService;
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
 *  - **Outbound** mail does NOT get it at send time. `EmailProcessingService` and
 *    `EmailController::editAndApprove` write `rfc_message_id` (the RFC 5322 Message-ID
 *    header we mint ourselves) and `gmail_thread_id`, because Gmail's send response does
 *    not return the id under which the message lands in SENT.
 *  - It is **back-filled later** by `IngestSentMail`, which walks SENT and matches on that
 *    header. That pass is scheduled, capped at `inbox.sent_ingest.limit` messages per run
 *    and bounded by `first_run_since` — so a recent send has no id yet, and older sends
 *    can be missed entirely if the cap was hit while the queue was long.
 *
 * The result on screen was "no Gmail id on record yet" for a scattering of perfectly
 * ordinary sent emails, with no way to act on them. That is the wrong answer: we know the
 * Message-ID header, and Gmail can be asked to find a message by it.
 *
 * ## What it does instead
 *
 * `rfc822msgid:` is a first-class Gmail search operator, so one `messages.list` call turns
 * a header into an API id. The id is written back to the row, which means the lookup is
 * paid once per email and every later read — including `IngestSentMail`'s own matching —
 * finds it already there.
 *
 * Deliberately NOT a fallback to `gmail_thread_id`. Trashing by thread would bin the
 * client's messages in that conversation too, which is not what anybody clicking "delete
 * the Gmail copy" on ONE message is asking for.
 */
class GmailCopy
{
    public function __construct(private readonly GmailService $gmail) {}

    /**
     * Gmail's API id for this email, looked up and persisted if we only had the header.
     *
     * Returns null when there is genuinely nothing to find: no ids at all, or Gmail has no
     * message under that Message-ID (already binned, or never reached SENT).
     */
    public function idFor(Email $email): ?string
    {
        if ($email->message_id) {
            return $email->message_id;
        }

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

        $id = $ids[0] ?? null;

        if (! $id) {
            return null;
        }

        // Back-filled, so this costs one lookup per email ever rather than one per delete.
        // forceFill: `message_id` is fillable, but this must not touch updated_at
        // semantics that anything else keys off, and saveQuietly keeps `email.updated`
        // from firing — the automation workflow listens to that, and learning an id is not
        // a change to the email.
        $email->forceFill(['message_id' => $id])->saveQuietly();

        return $id;
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

        return $email->rfc_message_id
            ? 'Gmail has no message under one email\'s Message-ID, so nothing was deleted there. It may already be in the Gmail bin.'
            : 'One sent message predates Message-ID tracking, so its Gmail copy could not be matched and was left alone.';
    }
}
