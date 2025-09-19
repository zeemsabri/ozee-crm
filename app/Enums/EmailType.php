<?php

namespace App\Enums;

/**
 * Email type values.
 */
enum EmailType: string
{
    case Received = 'received';
    case Sent = 'sent';
}
