<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\StripeConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Spatie\Activitylog\Models\Activity;

/**
 * @group External API
 *
 * APIs for external systems to interact with our payment and activity tracking system.
 */
class ExternalPaymentController extends Controller
{
    /**
     * Create Checkout Session
     *
     * Create a new Stripe Checkout session for external application payment tracking.
     *
     * @bodyParam app_id string required The unique ID for the application. Example: app-123
     * @bodyParam line_items array required List of items to be purchased. Example: [{"price_data": {"currency": "usd", "product_data": {"name": "Test Product"}, "unit_amount": 1000}, "quantity": 1}]
     * @bodyParam success_url url required The URL to redirect to after successful payment. Example: https://example.com/success
     * @bodyParam cancel_url url required The URL to redirect to after cancelled payment. Example: https://example.com/cancel
     * @bodyParam mode string The payment mode (payment_mode, subscription, setup). Default: payment. Example: payment
     * @bodyParam metadata object Extra metadata to store with the payment. Example: {"order_id": "123"}
     * @bodyParam user object The user information.
     * @bodyParam user.id string The ID of the user in the external system. Example: user-456
     * @bodyParam user.name string The name of the user. Example: John Doe
     * @bodyParam user.email string The email of the user. Example: john@example.com
     * @bodyParam user.phone string The phone number of the user. Example: +123456789
     *
     * @response {
     *  "success": true,
     *  "message": "Payment session created.",
     *  "data": {
     *    "session_id": "cs_test_...",
     *    "activity_id": 123,
     *    "checkout_url": "https://checkout.stripe.com/pay/...",
     *    "public_key": "pk_test_...",
     *    "expires_at": "2024-01-01 12:00:00"
     *  }
     * }
     */
    public function createSession(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'app_id' => 'required|string',
                'line_items' => 'required|array',
                'success_url' => 'required|url',
                'cancel_url' => 'required|url',
                'mode' => 'sometimes|string|in:payment,subscription,setup',
                'user' => 'sometimes|array',
                'user.id' => 'sometimes|string',
                'user.name' => 'sometimes|string',
                'user.email' => 'sometimes|email',
                'user.phone' => 'sometimes|string',
            ]);

            // Lookup the configuration
            $config = StripeConfiguration::where('app_id', $request->app_id)->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid app_id. Stripe configuration not found.',
                ], 404);
            }

            // Create an initial Activity Log entry
            $activity = activity('stripe_payment')
                ->withProperties([
                    'app_id' => $config->app_id,
                    'status' => 'pending',
                    'line_items' => $request->line_items,
                    'metadata' => $request->metadata ?? [],
                    'user' => $request->user ?? [],
                ])
                ->log("Stripe payment session initiated for {$config->app_name}");

            $activityId = $activity->id;

            // Initialize Stripe
            Stripe::setApiKey($config->stripe_secret_key);

            // Create the session
            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'line_items' => $request->line_items,
                'mode' => $request->mode ?? 'payment',
                'success_url' => $request->success_url . (strpos($request->success_url, '?') !== false ? '&' : '?') . 'activity_id=' . $activityId,
                'cancel_url' => $request->cancel_url . (strpos($request->cancel_url, '?') !== false ? '&' : '?') . 'activity_id=' . $activityId,
                'metadata' => array_merge($request->metadata ?? [], [
                    'app_id' => $config->app_id,
                    'app_name' => $config->app_name,
                    'activity_id' => $activityId,
                ]),
                'customer_email' => $request->input('user.email'),
            ]);

            // Update activity with session ID
            $activity->setRelation('subject', $config); // Associate with the config for easy lookups if needed
            $activity->properties = array_merge($activity->properties->toArray(), ['session_id' => $session->id]);
            $activity->save();

            Log::info('Stripe session created', [
                'app_id' => $config->app_id,
                'session_id' => $session->id,
                'activity_id' => $activityId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment session created.',
                'data' => [
                    'session_id' => $session->id,
                    'activity_id' => $activityId,
                    'checkout_url' => $session->url,
                    'public_key' => $config->stripe_public_key,
                    'expires_at' => date('Y-m-d H:i:s', $session->expires_at),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating Stripe session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment session: ' . $e->getMessage(),
            ], 500);
        }

    }

        /**
     * Get Payment Status
     *
     * Retrieve the current status of a payment session by its activity ID.
     *
     * @urlParam activity_id integer required The activity ID returned by create-session. Example: 123
     *
     * @response {
     *  "success": true,
     *  "data": {
     *    "id": 123,
     *    "status": "completed",
     *    "completed_at": "2024-01-01 12:05:00",
     *    "session_id": "cs_test_..."
     *  }
     * }
     * @response 404 {
     *  "success": false,
     *  "message": "Activity not found"
     * }
     */
    public function getStatus($activityId)
    {

        try {
            $activity = Activity::findOrFail($activityId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $activity->id,
                    'status' => $activity->getExtraProperty('status'),
                    'completed_at' => $activity->getExtraProperty('completed_at'),
                    'session_id' => $activity->getExtraProperty('session_id'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Activity not found',
            ], 404);
        }
    }
}
