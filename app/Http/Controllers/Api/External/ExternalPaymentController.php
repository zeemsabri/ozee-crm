<?php

namespace App\Http\Controllers\Api\External;

use App\Models\StripeConfiguration;
use App\Services\StripeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * @group External API
 *
 * APIs for external systems to interact with our payment and activity tracking system.
 */
class ExternalPaymentController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }
    /**
     * Create Checkout Session
     *
     * Create a new Stripe Checkout session for external application payment tracking.
     *
     * ### Example Payload: Payment Mode
     * ```json
     * {
     *     "app_id": "ifam-quiz-central",
     *     "mode": "payment",
     *     "line_items": [
     *         {
     *             "price_data": {
     *                 "currency": "aud",
     *                 "product_data": {
     *                     "name": "Quiz Enrollment",
     *                     "description": "Year 6"
     *                 },
     *                 "unit_amount": 2500
     *             },
     *             "quantity": 1
     *         }
     *     ],
     *     "success_url": "https://example.com/success",
     *     "cancel_url": "https://example.com/cancel"
     * }
     * ```
     *
     * ### Scenario 1: Lifetime Subscription (Unless Cancelled)
     * Standard recurring monthly billing that continues indefinitely.
     * ```json
     * {
     *     "app_id": "app-123",
     *     "mode": "subscription",
     *     "line_items": [{ "price": "price_abc_123", "quantity": 1 }],
     *     "success_url": "...", "cancel_url": "..."
     * }
     * ```
     * 
     * ### Scenario 2: Limited Subscription (3 Months)
     * To charge monthly but automatically cancel after 3 months, pass a Unix timestamp in `cancel_at`.
     * ```json
     * {
     *     "app_id": "app-123",
     *     "mode": "subscription",
     *     "line_items": [{ "price": "price_abc_123", "quantity": 1 }],
     *     "subscription_data": {
     *         "cancel_at": 1711432800
     *     },
     *     "success_url": "...", "cancel_url": "..."
     * }
     * ```
     * 
     * ### Scenario 3: Total Amount in Installments (e.g., 4 Payments)
     * To divide a cost into 4 parts, use a monthly price and set `cancel_at` to the date of the 4th payment.
     * ```json
     * {
     *     "app_id": "app-123",
     *     "mode": "subscription",
     *     "line_items": [{ "price": "price_25_per_month", "quantity": 1 }],
     *     "subscription_data": {
     *         "cancel_at": 1721887200
     *     },
     *     "success_url": "...", "cancel_url": "..."
     * }
     * ```
     * 
     * ### Scenario 4: Deposit + Remaining Installments
     * To charge $100 up front (deposit) and then $50/mo for 4 installments ($200 total), send two items: 
     * a recurring price ($50/mo) and a one-time price ($100).
     * ```json
     * {
     *     "app_id": "app-123",
     *     "mode": "subscription",
     *     "line_items": [
     *         { "price": "price_50_per_month", "quantity": 1 },
     *         { 
     *             "price_data": { 
     *                 "currency": "aud", 
     *                 "product_data": { "name": "Enrollment Deposit" },
     *                 "unit_amount": 10000 
     *             }, 
     *             "quantity": 1 
     *         }
     *     ],
     *     "subscription_data": { "cancel_at": 1721887200 },
     *     "success_url": "...", "cancel_url": "..."
     * }
     * ```
     * 
     * @bodyParam app_id string required The unique ID for the application. Example: app-123
     * @bodyParam line_items array Required for 'payment' and 'subscription' modes. Not used for 'setup'. List of items to be purchased. Supports providing a Stripe Price ID (`price`) or defining one on the fly (`price_data`). Example: [{"price_data": {"currency": "aud", "product_data": {"name": "Quiz Enrollment: Moustafa", "description": "IFAM Quiz 2026 - Year 6"}, "unit_amount": 2500}, "quantity": 1}]
     * @bodyParam success_url url required The URL to redirect to after successful payment. Example: https://example.com/success
     * @bodyParam cancel_url url required The URL to redirect to after cancelled payment. Example: https://example.com/cancel
     * @bodyParam mode string The payment mode (payment, subscription, setup). Default: payment. Different modes require different payloads. Example: payment
     * @bodyParam metadata object Extra metadata to store with the payment. Example: {"order_id": "123"}
     * @bodyParam allow_promotion_codes boolean Whether to enable the promotion code field on the checkout page. Default: false. Example: true
     * @bodyParam subscription_data object Options for subscription mode. Use `cancel_at` (Unix timestamp) to set an expiration date for installments. Example: {"trial_period_days": 7, "cancel_at": 1711432800}
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
                'line_items' => 'required_unless:mode,setup|array',
                'success_url' => 'required|url',
                'cancel_url' => 'required|url',
                'mode' => 'sometimes|string|in:payment,subscription,setup',
                'metadata' => 'sometimes|array',
                'allow_promotion_codes' => 'sometimes|boolean',
                'subscription_data' => 'sometimes|array',
                'subscription_data.trial_period_days' => 'sometimes|integer|min:1',
                'subscription_data.cancel_at' => 'sometimes|integer',
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
                ->causedBy($config)
                ->withProperties([
                    'app_id' => $config->app_id,
                    'status' => 'pending',
                    'line_items' => $request->line_items,
                    'metadata' => $request->metadata ?? [],
                    'user' => $request->user ?? [],
                ])
                ->log("Stripe payment session initiated for {$config->app_name}");

            $activityId = $activity->id;

            // Prepare the session payload
            $sessionPayload = [
                'payment_method_types' => ['card'],
                'mode' => $request->mode ?? 'payment',
                'success_url' => $request->success_url . (strpos($request->success_url, '?') !== false ? '&' : '?') . 'activity_id=' . $activityId,
                'cancel_url' => $request->cancel_url . (strpos($request->cancel_url, '?') !== false ? '&' : '?') . 'activity_id=' . $activityId,
                'metadata' => array_merge($request->metadata ?? [], [
                    'app_id' => $config->app_id,
                    'app_name' => $config->app_name,
                    'activity_id' => $activityId,
                ]),
                'allow_promotion_codes' => $request->allow_promotion_codes ?? false,
            ];

            if ($request->mode === 'subscription' && $request->has('subscription_data')) {
                $sessionPayload['subscription_data'] = $request->subscription_data;
            }

            if ($request->input('user.email')) {
                $sessionPayload['customer_email'] = $request->input('user.email');
            }

            if (($request->mode ?? 'payment') !== 'setup') {
                $sessionPayload['line_items'] = $request->line_items;
            }

            // Create the session via Service
            $session = $this->stripeService->createCheckoutSession($config, $sessionPayload);

            // Update activity with session ID
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
    /**
     * @group External API
     * Get Activities for Application
     *
     * Retrieve a list of payment activities associated with a specific application.
     *
     * @urlParam appId string required The application ID. Example: app-123
     *
     * @response {
     *  "success": true,
     *  "data": [
     *    {
     *      "id": 123,
     *      "description": "Stripe payment session initiated for Test App",
     *      "properties": {
     *        "app_id": "app-123",
     *        "status": "pending",
     *        "user": {"id": "user-456", "name": "John Doe"}
     *      },
     *      "created_at": "2024-01-01 12:00:00"
     *    }
     *  ]
     * }
     */
    public function getActivities($appId)
    {
        try {
            $config = StripeConfiguration::where('app_id', $appId)->firstOrFail();

            $activities = Activity::causedBy($config)
                ->latest()
                ->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $activities->items(),
                'meta' => [
                    'current_page' => $activities->currentPage(),
                    'last_page' => $activities->lastPage(),
                    'total' => $activities->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Application or activities not found',
            ], 404);
        }
    }

    /**
     * Create Price
     *
     * Create a new Stripe Price (and optionally a Product) for use in subsequent payment sessions.
     *
     *  ### Example Payload: Subscription Mode
     *  ```json
     *  {
     *      "app_id": "app-123",
     *      "product_name": "Premium Subscription",
     *      "product_id": "abc_123",
     *      "currency": "aud",
     *      "unit_amount": 1000,
     *      "recurring_interval": "month",
     *      "metadata": {
     *          "internal_id": 999
     *      }
     *  }
     *  ```
     *
     * @bodyParam app_id string required The unique ID for the application. Example: app-123
     * @bodyParam product_name string Required if product_id is not provided. The name of the product. Example: Premium Subscription
     * @bodyParam product_id string Required if product_name is not provided. The ID of an existing Stripe Product. Example: prod_123
     * @bodyParam currency string The currency for the price. Default: aud. Example: aud
     * @bodyParam unit_amount integer required The amount in cents. Example: 1000
     * @bodyParam recurring_interval string The interval for recurring payments (month, year, week, day). Example: month
     * @bodyParam metadata object Extra metadata to store with the price. Example: {"internal_id": "999"}
     *
     * @response {
     *  "success": true,
     *  "message": "Price created successfully.",
     *  "data": {
     *    "price_id": "price_...",
     *    "product_id": "prod_...",
     *    "currency": "aud",
     *    "unit_amount": 1000
     *  }
     * }
     */
    public function createPrice(Request $request)
    {
        try {
            $request->validate([
                'app_id' => 'required|string',
                'product_name' => 'required_without:product_id|string',
                'product_id' => 'required_without:product_name|string',
                'currency' => 'sometimes|string|size:3',
                'unit_amount' => 'required|integer|min:0',
                'recurring_interval' => 'sometimes|string|in:day,week,month,year',
                'recurring_interval_count' => 'sometimes|integer|min:1',
                'metadata' => 'sometimes|array',
            ]);

            $config = StripeConfiguration::where('app_id', $request->app_id)->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid app_id. Stripe configuration not found.',
                ], 404);
            }

            // Log activity
            activity('stripe_payment')
                ->causedBy($config)
                ->withProperties([
                    'app_id' => $config->app_id,
                    'action' => 'create_price',
                    'payload' => $request->all(),
                ])
                ->log("Stripe price creation initiated for {$config->app_name}");

            $price = $this->stripeService->createPrice($config, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Price created successfully.',
                'data' => [
                    'price_id' => $price->id,
                    'product_id' => $price->product,
                    'currency' => $price->currency,
                    'unit_amount' => $price->unit_amount,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating Stripe price: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create price: ' . $e->getMessage(),
            ], 500);
        }
    }
}
