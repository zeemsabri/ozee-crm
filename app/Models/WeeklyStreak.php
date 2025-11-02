<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyStreak extends Model
{
    use HasFactory;

    const WEEKLY_STREAK_BONUS = 500;

    protected $fillable = ['id'];
}
