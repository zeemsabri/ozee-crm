<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailApp extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'delivery_mode',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_address',
        'smtp_from_name',
        'smtp_reply_to',
        'api_provider',
        'api_base_url',
        'api_key',
        'api_secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'smtp_port' => 'integer',
        'smtp_password' => 'encrypted',
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
    ];

    protected $hidden = [
        'smtp_password',
        'api_key',
        'api_secret',
    ];

    public function magicLinks(): HasMany
    {
        return $this->hasMany(MagicLink::class);
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(EmailTemplate::class, 'email_app_template', 'email_app_id', 'email_template_id')
            ->withTimestamps();
    }

    public function externalEmailLogs(): HasMany
    {
        return $this->hasMany(ExternalEmailLog::class);
    }
}
