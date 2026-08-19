<?php

namespace App\Enums;

/**
 * Where an email sits with the AI checker.
 *
 * Stored as a plain string on emails.ai_status (see the migration for why it is not a DB
 * enum). Null — the common case — means the email was never submitted to the checker at
 * all, which is different from Failed.
 */
enum EmailAiStatus: string
{
    /** Queued for checking; the job has not started. */
    case Queued = 'queued';

    /** The job is running. Nobody edits a draft in this state. */
    case Checking = 'checking';

    /** Cleared. An outbound draft in this state may auto-send. */
    case Approved = 'approved';

    /** Sent back with a reason. Needs a human before it can go out. */
    case Held = 'held';

    /** The checker could not reach a verdict (no key, API error, unparseable reply). */
    case Failed = 'failed';

    /** States where the email is locked because the checker has it. */
    public function isPending(): bool
    {
        return $this === self::Queued || $this === self::Checking;
    }

    /** States that need a person to act. */
    public function needsHuman(): bool
    {
        return $this === self::Held || $this === self::Failed;
    }
}
