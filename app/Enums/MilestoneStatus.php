<?php

namespace App\Enums;

/**
 * Canonical list of Milestone status values.
 * Backed by strings to remain backward compatible with existing DB values and logic.
 */
enum MilestoneStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case InProgress = 'in progress';
    case Overdue = 'overdue';
    case Canceled = 'canceled';
    case Expired = 'expired';
    case PendingApproval = 'pending approval';
    case PendingReview = 'pending review';
}
