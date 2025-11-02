<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurrencyRate;
use Illuminate\Http\JsonResponse; // Import the CurrencyRate model
use Illuminate\Support\Facades\Cache;

class CurrencyController extends Controller
{
    /**
     * Get currency conversion rates from the database.
     * Caches the rates for a day to reduce database hits.
     */
    public function index(): JsonResponse
    {
        // Cache rates for 24 hours (or until new rates are fetched by the job)
        $rates = Cache::remember('currency_rates_to_usd', 60 * 24, function () {
            $dbRates = CurrencyRate::all()->pluck('rate_to_usd', 'currency_code')->toArray();
            // Ensure USD is always 1.0 as a base, even if not explicitly in DB
            if (! isset($dbRates['USD'])) {
                $dbRates['USD'] = 1.0;
            }

            return $dbRates;
        });

        return response()->json($rates);
    }
}
