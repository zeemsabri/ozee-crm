<?php

namespace App\Services;

use App\Models\StripeConfiguration;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Price;
use Stripe\Product;

class StripeService
{
    /**
     * Create a Stripe Checkout Session.
     */
    public function createCheckoutSession(StripeConfiguration $config, array $sessionPayload)
    {
        Stripe::setApiKey($config->stripe_secret_key);
        return StripeSession::create($sessionPayload);
    }

    /**
     * Create a Stripe Price (and optionally a Product).
     */
    public function createPrice(StripeConfiguration $config, array $data)
    {
        Stripe::setApiKey($config->stripe_secret_key);

        $pricePayload = [
            'currency' => $data['currency'] ?? 'aud',
            'unit_amount' => $data['unit_amount'],
        ];

        if (isset($data['product_id'])) {
            $pricePayload['product'] = $data['product_id'];
        } else {
            // Create product on the fly if name is provided
            $pricePayload['product_data'] = [
                'name' => $data['product_name'] ?? 'Custom Item',
            ];
            
            if (isset($data['product_description'])) {
                $pricePayload['product_data']['description'] = $data['product_description'];
            }
        }

        if (isset($data['recurring_interval'])) {
            $pricePayload['recurring'] = [
                'interval' => $data['recurring_interval'], // e.g., 'month', 'year'
            ];

            if (isset($data['recurring_interval_count'])) {
                $pricePayload['recurring']['interval_count'] = $data['recurring_interval_count'];
            }
        }

        if (isset($data['metadata'])) {
            $pricePayload['metadata'] = $data['metadata'];
        }

        return Price::create($pricePayload);
    }
}
