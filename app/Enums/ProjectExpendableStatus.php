<?php

namespace App\Enums;

/**
 * Approval status for ProjectExpendable entries.
 */
enum ProjectExpendableStatus: string
{
    case PendingApproval = 'Pending Approval';
    case Accepted = 'Accepted';
    case Rejected = 'Rejected';
}
