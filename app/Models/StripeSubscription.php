<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeSubscription extends Model
{
    protected $fillable = [
        'app_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'status',
        'amount_total',
        'currency',
        'cancel_at',
        'canceled_at',
        'ended_at',
        'metadata',
    ];

    protected $casts = [
        'cancel_at' => 'datetime',
        'canceled_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function payments()
    {
        return $this->hasMany(StripeSubscriptionPayment::class, 'stripe_subscription_id', 'stripe_subscription_id');
    }
}
