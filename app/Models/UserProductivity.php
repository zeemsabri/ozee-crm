<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProductivity extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'stats_json',
        'tasks_json',
        'timeline_json',
        'ai_report_json',
        'accuracy_json',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'stats_json' => 'array',
        'tasks_json' => 'array',
        'timeline_json' => 'array',
        'ai_report_json' => 'array',
        'accuracy_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
