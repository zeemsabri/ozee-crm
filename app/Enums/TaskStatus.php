<?php

namespace App\Enums;

/**
 * Canonical list of Task status values.
 * Backed by strings to remain backward compatible with existing DB values and logic.
 */
enum TaskStatus: string
{
    case ToDo = 'To Do';
    case InProgress = 'In Progress';
    case Paused = 'Paused';
    case Done = 'Done';
    case Blocked = 'Blocked';
    case Archived = 'Archived';
}
