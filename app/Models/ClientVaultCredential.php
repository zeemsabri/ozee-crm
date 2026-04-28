<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ClientVaultCredential extends Model
{
    use HasFactory, SoftDeletes;

    public const SOURCE_CLIENT = 'client';
    public const SOURCE_TEAM = 'team';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'client_id',
        'project_id',
        'created_by',
        'source',
        'is_visible_to_client',
        'label',
        'encrypted_username',
        'encrypted_password',
        'encrypted_pin',
        'salt',
        'expires_at',
        'last_viewed_at',
    ];

    protected $casts = [
        'is_visible_to_client' => 'boolean',
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

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sharedWithUsers()
    {
        return $this->belongsToMany(User::class, 'client_vault_credential_user', 'client_vault_credential_id', 'user_id')
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function isClientSubmitted(): bool
    {
        return $this->source === self::SOURCE_CLIENT;
    }

    public function isTeamSubmitted(): bool
    {
        return $this->source === self::SOURCE_TEAM;
    }
}
