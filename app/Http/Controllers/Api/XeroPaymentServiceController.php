<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\XeroPaymentServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XeroPaymentServiceController extends Controller
{
    public function __construct(private readonly XeroPaymentServiceCatalog $paymentServiceCatalog)
    {
    }

    public function index(): JsonResponse
    {
        try {
            $services = $this->paymentServiceCatalog->listCached(refreshIfStale: true);

            return response()->json([
                'payment_services' => $services,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'payment_services' => [],
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function refresh(Request $request): JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can refresh payment services.'], 403);
        }

        try {
            $services = $this->paymentServiceCatalog->syncCache();

            return response()->json([
                'payment_services' => $services,
                'message' => 'Payment services refreshed successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'payment_services' => [],
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
