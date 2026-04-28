<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\StripeConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;
use Stripe\Stripe;
use Stripe\Webhook;

/**
 * @group External API
 *
 * APIs for external systems to interact with our payment and activity tracking system.
 */
class StripeWebhookController extends Controller
{
    /**
     * Handle Stripe Webhook
     *
     * Receive and process Stripe webhook events.
     *
     * ### Stripe Configuration:
     * 1. Go to Stripe Dashboard > Developers > Webhooks.
     * 2. Add an endpoint: `https://your-domain.com/api/external/stripe/webhook/{app_id}`
     * 3. Select events:
     *    - `checkout.session.completed` (Primary payment)
     *    - `customer.subscription.created` (New subscription)
     *    - `customer.subscription.updated` (Status changes like past_due)
     *    - `customer.subscription.deleted` (Subscription cancelled/ended)
     *    - `invoice.payment_succeeded` (Recurring payment received)
     * 4. Copy the "Signing secret" and save it in `/admin/stripe-configurations` for the matching `app_id`.
     *
     * @authentication
     * @unauthenticated
     *
     * @urlParam app_id string required The unique ID for the application. Example: app-123
     * @header Stripe-Signature string required The signature from Stripe for security verification.
     *
     * @response 200 {
     *  "status": "success"
     * }
     */
    public function handle(Request $request, $app_id)
    {
        $config = StripeConfiguration::where('app_id', $app_id)->first();

        if (!$config) {
            Log::error("Stripe Webhook Error: Configuration for app_id {$app_id} not found.");
            return response()->json(['error' => 'Config not found'], 404);
        }

        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        // We'll try to find the webhook secret in the config settings
        $webhookSecret = $config->settings['webhook_secret'] ?? null;

        try {
            if ($webhookSecret && $sig_header) {
                $event = Webhook::constructEvent($payload, $sig_header, $webhookSecret);
            } else {
                // Fallback for testing or if secret is not set (not recommended for production)
                $event = json_decode($payload, false);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON payload');
                }
            }
        } catch (\Exception $e) {
            Log::error("Stripe Webhook Error: " . $e->getMessage());
            return response()->json(['error' => 'Invalid payload or signature'], 400);
        }

        Log::info("Stripe Webhook Received: " . json_encode($event->data));

        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSession($event->data->object, $config, 'succeeded');
                break;
            case 'checkout.session.expired':
                $this->handleCheckoutSession($event->data->object, $config, 'expired');
                break;
            case 'checkout.session.async_payment_failed':
                $this->handleCheckoutSession($event->data->object, $config, 'failed');
                break;
            case 'customer.subscription.created':
                $this->handleSubscriptionChange($event->data->object, $app_id);
                break;
            case 'customer.subscription.updated':
                $this->handleSubscriptionChange($event->data->object, $app_id);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionChange($event->data->object, $app_id);
                break;
            case 'invoice.payment_succeeded':
                $this->handleInvoicePaid($event->data->object, $app_id);
                break;
            // Add more types as needed (charge.succeeded, invoice.paid, etc.)
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Update activity log and execute logic for checkout sessions.
     */
    protected function handleCheckoutSession($session, $config, $status)
    {
        $metadata = $session->metadata ?? [];
        $metaArray = is_object($metadata) ? (method_exists($metadata, 'toArray') ? $metadata->toArray() : (array)$metadata) : (array)$metadata;

        // Ensure it's a flat metadata array
        if (isset($metaArray['metadata'])) {
            $innerMeta = is_object($metaArray['metadata']) ? (method_exists($metaArray['metadata'], 'toArray') ? $metaArray['metadata']->toArray() : (array)$metaArray['metadata']) : (array)$metaArray['metadata'];
            $metaArray = $innerMeta;
        }

        $activityId = $metaArray['activity_id'] ?? null;

        if ($activityId) {
            $activity = Activity::find($activityId);
            if ($activity) {
                $properties = $activity->properties->toArray();
                $properties['status'] = $status;
                $properties['completed_at'] = now()->toDateTimeString();
                $properties['stripe_session_id'] = $session->id;

                // If this is a subscription, store the ID
                if ($session->subscription) {
                    $properties['stripe_subscription_id'] = $session->subscription;
                }

                $activity->properties = $properties;
                $activity->save();

                Log::info("Payment {$status} for activity {$activityId}");
            }
        }

        if ($status === 'succeeded') {
            // Handle auto-cancellation and metadata updates if requested in metadata
            if ($session->subscription) {
                try {
                    $updatePayload = [];
                    if (isset($metaArray['auto_cancel_at'])) {
                        $updatePayload['cancel_at'] = (int) $metaArray['auto_cancel_at'];
                    }

                    // Sync all metadata to the subscription
                    unset($metaArray['auto_cancel_at']); // Don't re-save this in subscription metadata

                    if (!empty($metaArray)) {
                        $updatePayload['metadata'] = $metaArray;
                    }

                    if (!empty($updatePayload)) {
                        \Stripe\Stripe::setApiKey($config->stripe_secret_key);
                        \Stripe\Subscription::update($session->subscription, $updatePayload);
                        Log::info("Updated subscription {$session->subscription} with " . json_encode($updatePayload));
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to update subscription metadata: " . $e->getMessage());
                }
            }

            $this->executeWebhookLogic($session, $config);
        }
    }

    /**
     * Execute dynamic logic defined in the StripeConfiguration.
     */
    protected function executeWebhookLogic($session, $config)
    {
        $logic = $config->settings['webhook_logic'] ?? [];

        foreach ($logic as $rule) {
            if (isset($rule['event']) && $rule['event'] === 'checkout.session.completed') {
                $actions = $rule['actions'] ?? [];
                foreach ($actions as $action) {
                    try {
                        $this->performAction($action, $session);
                    } catch (\Exception $e) {
                        Log::error("Webhook Action Error: " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Perform a specific action (e.g., updating a model).
     */
    protected function performAction($action, $session)
    {
        Log::info("Performing webhook action: " . ($action['type'] ?? 'unknown'));

        switch ($action['type'] ?? '') {
            case 'update_model':
                $modelClass = $action['model'] ?? null;
                $queryField = $action['query_field'] ?? 'id';
                $metadataField = $action['metadata_field'] ?? null;
                $updateData = $action['update'] ?? [];

                if (!$modelClass || !$metadataField) {
                    Log::error("Webhook Action Error: Missing model or metadata field.");
                    return;
                }

                $metadata = $session->metadata ?? [];
                $metaArray = is_object($metadata) ? (method_exists($metadata, 'toArray') ? $metadata->toArray() : (array)$metadata) : (array)$metadata;
                if (isset($metaArray['metadata'])) {
                    $inner = is_object($metaArray['metadata']) ? (method_exists($metaArray['metadata'], 'toArray') ? $metaArray['metadata']->toArray() : (array)$metaArray['metadata']) : (array)$metaArray['metadata'];
                    $metaArray = $inner;
                }

                $ids = $metaArray[$metadataField] ?? null;

                // metadata values in Stripe are always strings
                if (is_string($ids)) {
                    // Try to decode if it looks like JSON, otherwise explode if it has commas
                    if (str_starts_with($ids, '[') || str_starts_with($ids, '{')) {
                        $ids = json_decode($ids, true);
                    } elseif (str_contains($ids, ',')) {
                        $ids = array_map('trim', explode(',', $ids));
                    } else {
                        $ids = [$ids];
                    }
                }

                if (!empty($ids)) {
                    if (class_exists($modelClass)) {
                        $modelClass::whereIn($queryField, (array)$ids)->update($updateData);
                        Log::info("Webhook Action: Updated " . count((array)$ids) . " {$modelClass} records.");
                    } else {
                        Log::error("Webhook Action: Model {$modelClass} not found.");
                    }
                }
                break;
        }
    }

    /**
     * Handle subscription created or deleted events.
     */
    protected function handleSubscriptionChange($subscription, $app_id)
    {
        $metadata = $subscription->metadata ?? [];
        $metadataArr = is_object($metadata) ? (method_exists($metadata, 'toArray') ? $metadata->toArray() : (array)$metadata) : (array)$metadata;

        // Ensure it's a flat metadata array
        if (isset($metadataArr['metadata'])) {
            $innerMeta = is_object($metadataArr['metadata']) ? (method_exists($metadataArr['metadata'], 'toArray') ? $metadataArr['metadata']->toArray() : (array)$metadataArr['metadata']) : (array)$metadataArr['metadata'];
            $metadataArr = $innerMeta;
        }

        // Parent metadata if any
        $parentMeta = [];
        $parentDetails = is_object($subscription->parent ?? null) ? (method_exists($subscription->parent, 'toArray') ? $subscription->parent->toArray() : (array)$subscription->parent) : (array)($subscription->parent ?? []);

        if (isset($parentDetails['subscription_details']['metadata'])) {
            $pm = $parentDetails['subscription_details']['metadata'];
            $parentMeta = is_object($pm) ? (method_exists($pm, 'toArray') ? $pm->toArray() : (array)$pm) : (array)$pm;
        }

        // We check for total_amount from current metadata or parent metadata
        $amount_total = null;
        if (isset($metadataArr['total_amount']) && $metadataArr['total_amount'] !== '') {
            $amount_total = $metadataArr['total_amount'];
        } elseif (isset($parentMeta['total_amount']) && $parentMeta['total_amount'] !== '') {
            $amount_total = $parentMeta['total_amount'];
        }

        $existing = \App\Models\StripeSubscription::where('stripe_subscription_id', $subscription->id)->first();
        if ($amount_total === null && $existing && $existing->amount_total) {
            $amount_total = $existing->amount_total;
        }

        \App\Models\StripeSubscription::updateOrCreate(
            ['stripe_subscription_id' => $subscription->id],
            [
                'app_id' => $app_id,
                'stripe_customer_id' => $subscription->customer,
                'status' => $subscription->status,
                'cancel_at' => $subscription->cancel_at ? date('Y-m-d H:i:s', $subscription->cancel_at) : null,
                'canceled_at' => $subscription->canceled_at ? date('Y-m-d H:i:s', $subscription->canceled_at) : null,
                'ended_at' => $subscription->ended_at ? date('Y-m-d H:i:s', $subscription->ended_at) : null,
                'metadata' => empty($metadataArr) ? null : $metadataArr,
                'amount_total' => $amount_total,
                'currency' => $subscription->currency ?? 'aud',
            ]
        );

        Log::info("Handled subscription change for {$subscription->id} (status: {$subscription->status})");
    }

    /**
     * Handle invoice paid event (triggers for each installment/recurring payment).
     */
    protected function handleInvoicePaid($invoice, $app_id)
    {
        // In newer Stripe API versions, subscription is under parent.subscription_details.subscription
        // or lines.data[0].parent.subscription_item_details.subscription
        $subscriptionId = $invoice->subscription ??
                         ($invoice->parent['subscription_details']['subscription'] ?? null);

        if (!$subscriptionId) {
            Log::warning("No subscription ID found in invoice: " . $invoice->id);
            return;
        }

        $payment = \App\Models\StripeSubscriptionPayment::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'stripe_subscription_id' => $subscriptionId,
                'amount' => $invoice->amount_paid,
                'currency' => $invoice->currency,
                'status' => 'paid',
                'paid_at' => now(),
            ]
        );

        Log::info("Logged payment for subscription {$subscriptionId} in app {$app_id}");

        // Check if we need to auto-cancel because total amount has been reached
        $localSubscription = \App\Models\StripeSubscription::where('stripe_subscription_id', $subscriptionId)->first();
        if ($localSubscription && $localSubscription->amount_total > 0) {
            $totalCollected = \App\Models\StripeSubscriptionPayment::where('stripe_subscription_id', $subscriptionId)
                ->where('status', 'paid')
                ->sum('amount');

            if ($totalCollected >= $localSubscription->amount_total) {
                try {
                    $config = StripeConfiguration::where('app_id', $app_id)->first();
                    if ($config) {
                        \Stripe\Stripe::setApiKey($config->stripe_secret_key);
                        $stripeSub = \Stripe\Subscription::retrieve($subscriptionId);

                        // Cancel the subscription so it doesn't bill again
                        $stripeSub->cancel();

                        Log::info("Auto-cancelled subscription {$subscriptionId} because total amount {$totalCollected} reached/exceeded goal {$localSubscription->amount_total}");
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to auto-cancel subscription after reaching total amount: " . $e->getMessage());
                }
            }
        }
    }
}
