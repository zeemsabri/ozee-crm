<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\XeroWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XeroWebhookController extends Controller
{
    public function __construct(private readonly XeroWebhookService $xeroWebhookService) {}

    public function handle(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('x-xero-signature');

        if (! $this->xeroWebhookService->hasValidSignature($rawPayload, $signature)) {
            return response()->json([
                'message' => 'Invalid Xero webhook signature.',
            ], 401);
        }

        $payload = $request->json()->all();

        if (! is_array($payload)) {
            return response()->json([
                'message' => 'Invalid Xero webhook payload.',
            ], 400);
        }

        if ($this->xeroWebhookService->isIntentToReceive($payload)) {
            return response()->json([
                'status' => 'ok',
                'intent' => true,
            ]);
        }

        $summary = $this->xeroWebhookService->process($payload);

        return response()->json([
            'status' => 'ok',
            'summary' => $summary,
        ]);
    }
}
