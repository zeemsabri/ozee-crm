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
}
