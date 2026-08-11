<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripePayout extends Model
{
    use HasFactory;

    protected $table = 'stripe_payouts';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'trace_id',
        'statement_descriptor',
        'amount',
        'currency',
        'status',
        'arrival_date',
        'total_gross',
        'total_fees',
        'total_net',
        'breakdown',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'total_gross' => 'decimal:2',
        'total_fees' => 'decimal:2',
        'total_net' => 'decimal:2',
        'arrival_date' => 'date',
        'breakdown' => 'array',
    ];
}
