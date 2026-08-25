<?php

namespace App\Enums;

/**
 * Canonical list of Email status values.
 * Backed by strings for DB compatibility.
 */
enum EmailStatus: string
{
    // Matches existing strings used in Email model
    case PendingApprovalReceived = 'pending_approval_received';
    case PendingApproval = 'pending_approval';
    case RejectedReceived = 'rejected_received';
    case Rejected = 'rejected';
    case Received = 'received';
    case Sent = 'sent';
    case Draft = 'draft';
    case Unknown = 'unknown';
    case Pending = 'pending';
    case Approved = 'approved';
    case AutoSend = 'auto_send';
    case Delayed = 'delayed';
    // Parked by its author, NOT submitted. Deliberately distinct from Draft, which the
    // workflow automation selects on: a saved email must never enter that pipeline.
    // Written only by Api\InboxSavedController; a saved row has no conversation, which is
    // what keeps it out of every thread/list/quote query. See the 2026_08_26_100000
    // migration for the full story.
    case Saved = 'saved';
}
