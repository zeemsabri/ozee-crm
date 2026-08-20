<?php

namespace App\Enums;

/**
 * Where a "Draft for me" request has got to.
 *
 * Deliberately separate from EmailAiStatus. That one tracks the OUTBOUND checker deciding
 * whether an email may be sent — it gates the "With AI" view and, when the checker is on,
 * real mail. This one tracks a suggestion being written for a human to edit, which sends
 * nothing and blocks nothing. Sharing an enum between them would put a drafting email in
 * the approval queue.
 */
enum EmailDraftStatus: string
{
    /** Dispatched, not yet picked up by a worker. */
    case Queued = 'queued';

    /** A worker has it and the model is being called. */
    case Writing = 'writing';

    /** A draft was produced and is on the email. */
    case Ready = 'ready';

    /** The model returned nothing usable, or the job threw. Offer to try again. */
    case Failed = 'failed';

    /** Still working — the UI shows progress and keeps polling. */
    public function isWorking(): bool
    {
        return $this === self::Queued || $this === self::Writing;
    }
}
