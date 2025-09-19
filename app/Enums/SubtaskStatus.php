<?php

namespace App\Enums;

/**
 * Canonical list of Subtask status values.
 * Backed by strings to remain backward compatible with existing DB values and logic.
 */
enum SubtaskStatus: string
{
    case ToDo = 'To Do';
    case InProgress = 'In Progress';
    case Done = 'Done';
    case Blocked = 'Blocked';
}
