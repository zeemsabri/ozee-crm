<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirwallexXeroBankMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'xero_connection_id',
        'airwallex_currency',
        'xero_account_id',
        'xero_account_name',
        'xero_currency_code',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(XeroConnection::class, 'xero_connection_id');
    }
}
