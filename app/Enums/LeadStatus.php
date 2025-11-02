<?php

namespace App\Enums;

/**
 * Canonical list of Lead status values.
 * Backed by strings for DB compatibility and easy serialization.
 */
enum LeadStatus: string
{
    case New = 'new';

    case HOT_INCOMING = 'hot_incoming';
    case HOT_OUTGOING = 'hot_outgoing';
    case Processing = 'processing';
    case Contacted = 'contacted';
    case GenerationFailed = 'generation_failed';
    case SequenceCompleted = 'sequence_completed';
    case Converted = 'converted';

    case Lost = 'lost';

    case QUALIFIED = 'qualified';

    case OUTREACH_SENT = 'outreach_sent';

}
