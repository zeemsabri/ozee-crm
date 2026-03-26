<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeSubscriptionPayment extends Model
{
    protected $fillable = [
        'stripe_subscription_id',
        'stripe_invoice_id',
        'amount',
        'currency',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(StripeSubscription::class, 'stripe_subscription_id', 'stripe_subscription_id');
    }
}
