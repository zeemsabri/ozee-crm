<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\StripeConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhooks.
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

        Log::info("Received Stripe Webhook: {$event->type} for app_id: {$app_id}");

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
            // Add more types as needed (charge.succeeded, invoice.paid, etc.)
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Update activity log and execute logic for checkout sessions.
     */
    protected function handleCheckoutSession($session, $config, $status)
    {
        $metadata = $session->metadata ?? (object)[];
        $activityId = $metadata->activity_id ?? null;

        if ($activityId) {
            $activity = Activity::find($activityId);
            if ($activity) {
                $properties = $activity->properties->toArray();
                $properties['status'] = $status;
                $properties['completed_at'] = now()->toDateTimeString();
                $properties['stripe_session_id'] = $session->id;
                
                $activity->properties = $properties;
                $activity->save();
                
                Log::info("Payment {$status} for activity {$activityId}");
            }
        }

        if ($status === 'succeeded') {
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

                $ids = $session->metadata->{$metadataField} ?? null;
                
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
            
            // Placeholder for other action types
            case 'log':
                Log::info("Webhook Manual Log: " . ($action['message'] ?? 'Action triggered'));
                break;
        }
    }
}
