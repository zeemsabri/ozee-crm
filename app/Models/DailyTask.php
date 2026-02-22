<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyTask extends Model
{
    use HasFactory;

    protected $table = 'daily_tasks';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PUSHED = 'pushed_to_next_day';

    protected $fillable = [
        'user_id',
        'task_id',
        'date',
        'order',
        'status',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'order' => 'integer',
    ];

    /**
     * The user who owns this work log entry.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The underlying Task.
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Scope: for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: for a specific date.
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('date', $date);
    }

    /**
     * Scope: ordered by position.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
