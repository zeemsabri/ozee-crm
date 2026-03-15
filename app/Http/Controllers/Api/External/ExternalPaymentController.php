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

class ExternalPaymentController extends Controller
{
    /**
     * Create a payment session for external systems.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
     * Check the status of a payment activity.
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
