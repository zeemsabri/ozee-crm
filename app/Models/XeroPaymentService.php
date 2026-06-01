<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XeroPaymentService extends Model
{
    use HasFactory;

    protected $fillable = [
        'xero_connection_id',
        'payment_service_id',
        'name',
        'slug',
        'status',
        'provider',
        'raw_payload',
        'last_synced_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(XeroConnection::class, 'xero_connection_id');
    }
}
