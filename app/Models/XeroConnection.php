<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class XeroConnection extends Model
{
    use HasFactory;

    public const PROVIDER = 'xero';

    protected $fillable = [
        'provider',
        'connected_by_user_id',
        'status',
        'selected_tenant_id',
        'selected_tenant_name',
        'access_token',
        'refresh_token',
        'id_token',
        'scope',
        'token_type',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'last_refreshed_at',
        'last_connected_at',
        'disconnected_at',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'id_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'last_refreshed_at' => 'datetime',
        'last_connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'id_token',
    ];

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(XeroTenant::class);
    }

    public function paymentServices(): HasMany
    {
        return $this->hasMany(XeroPaymentService::class, 'xero_connection_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected'
            && filled($this->access_token)
            && filled($this->refresh_token)
            && filled($this->selected_tenant_id);
    }

    public function needsRefresh(int $bufferMinutes = 5): bool
    {
        if (! $this->access_token_expires_at instanceof Carbon) {
            return true;
        }

        return $this->access_token_expires_at->lte(now()->addMinutes($bufferMinutes));
    }
}