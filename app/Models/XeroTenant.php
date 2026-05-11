<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XeroTenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'xero_connection_id',
        'tenant_id',
        'tenant_name',
        'tenant_type',
        'auth_event_id',
        'connection_id',
        'created_date_utc',
        'updated_date_utc',
        'is_selected',
    ];

    protected $casts = [
        'created_date_utc' => 'datetime',
        'updated_date_utc' => 'datetime',
        'is_selected' => 'boolean',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(XeroConnection::class, 'xero_connection_id');
    }
}