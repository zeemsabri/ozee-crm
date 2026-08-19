<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside the guest bill-upload transaction when the invoice would take the
 * contract past what is left to bill.
 *
 * It exists so the overbilling check can run *inside* the locked transaction — where
 * it is race-free — while still surfacing as a plain 422 to the supplier rather than a
 * 500. Carries the user-facing sentence as its message.
 */
class BillExceedsContractException extends RuntimeException
{
}
