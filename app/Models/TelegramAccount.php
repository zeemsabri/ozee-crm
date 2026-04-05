<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class TelegramAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_id',
        'telegramable_id',
        'telegramable_type',
        'username',
        'first_name',
        'last_name',
    ];

    public function telegramable()
    {
        return $this->morphTo();
    }
}
