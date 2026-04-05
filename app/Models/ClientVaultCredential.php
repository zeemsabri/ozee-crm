<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClientVaultCredential extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'client_id',
        'label',
        'encrypted_username',
        'encrypted_password',
        'salt',
        'expires_at',
        'last_viewed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_viewed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
