<?php

namespace App\Enums;

/**
 * Canonical list of Lead status values.
 * Backed by strings for DB compatibility and easy serialization.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Processing = 'processing';
    case Contacted = 'contacted';
    case GenerationFailed = 'generation_failed';
    case SequenceCompleted = 'sequence_completed';
    case Converted = 'converted';
}
