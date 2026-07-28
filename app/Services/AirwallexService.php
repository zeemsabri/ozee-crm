<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class AirwallexService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $apiKey;
    protected string $tokenCacheKey = 'airwallex_bearer_token';

    public function __construct()
    {
        $this->baseUrl = config('services.airwallex.base_url', 'https://api.airwallex.com');
        $this->clientId = config('services.airwallex.client_id');
        $this->apiKey = config('services.airwallex.api_key');
    }

    /**
     * Authenticate with Airwallex to get a Bearer token.
     * Caches the token to avoid redundant API calls.
     *
     * @return string
     * @throws Exception
     */
    public function authenticate(): string
    {
        if (Cache::has($this->tokenCacheKey)) {
            return Cache::get($this->tokenCacheKey);
        }

        $response = Http::withHeaders([
            'x-client-id' => $this->clientId,
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/api/v1/authentication/login');

        if ($response->successful()) {
            $token = $response->json('token');
            // Cache token for 30 minutes (Airwallex tokens typically expire in 30 mins)
            Cache::put($this->tokenCacheKey, $token, now()->addMinutes(30));
            return $token;
        }

        Log::error('Airwallex Authentication Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new Exception('Failed to authenticate with Airwallex: ' . $response->body());
    }

    /**
     * Get transactions from Airwallex.
     *
     * @param array $params
     * @return array
     * @throws Exception
     */
    public function getTransactions(array $params = []): array
    {
        $token = $this->authenticate();

        $response = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->get($this->baseUrl . '/api/v1/financial_transactions', $params);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('Airwallex Get Transactions Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'params' => $params,
        ]);

        throw new Exception('Failed to fetch Airwallex transactions: ' . $response->body());
    }
}
