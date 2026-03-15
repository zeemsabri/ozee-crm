<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeConfiguration extends Model
{
    protected $fillable = [
        'app_name',
        'app_id',
        'stripe_secret_key',
        'stripe_public_key',
        'settings',
    ];

    protected $casts = [
        'stripe_secret_key' => 'encrypted',
        'settings' => 'array',
    ];

    protected $hidden = [
        'stripe_secret_key',
    ];
}
