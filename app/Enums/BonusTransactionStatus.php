<?php

namespace App\Enums;

/**
 * Status values for BonusTransaction records.
 * Backed by strings for DB compatibility.
 */
enum BonusTransactionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Processed = 'processed';
}
