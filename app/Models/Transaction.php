<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'description',
        'amount',
        'currency',
        'user_id',
        'hours_spent',
        'type',
        'curency',
        'is_paid'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => 'string',
        'hours_spent' => 'decimal:2',
        'type' => 'string',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
