<?php

namespace App\Enums;

/**
 * Canonical list of Project status values.
 * Backed by strings for DB compatibility and easy serialization.
 */
enum ProjectStatus: string
{
    case Active = 'active';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Canceled = 'canceled';
}
