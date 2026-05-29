<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\XeroTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class XeroAccountController extends Controller
{
    public function __construct(private readonly XeroTokenService $xeroTokenService)
    {
    }

    public function index(): JsonResponse
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->get('https://api.xero.com/api.xro/2.0/Accounts')
            ->throw()
            ->json();

        $allowedTypes = ['EXPENSE', 'OVERHEADS', 'DIRECTCOSTS'];

        $accounts = collect(data_get($response, 'Accounts', []))
            ->filter(function (array $account) use ($allowedTypes) {
                return data_get($account, 'Status') === 'ACTIVE'
                    && in_array(data_get($account, 'Type'), $allowedTypes, true)
                    && filled(data_get($account, 'Code'));
            })
            ->map(function (array $account) {
                return [
                    'code' => strtoupper((string) data_get($account, 'Code')),
                    'name' => (string) data_get($account, 'Name', ''),
                    'type' => (string) data_get($account, 'Type', ''),
                ];
            })
            ->sortBy('code')
            ->values();

        return response()->json($accounts);
    }

    public function items(): JsonResponse
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->get('https://api.xero.com/api.xro/2.0/Items')
            ->throw()
            ->json();

        $items = collect(data_get($response, 'Items', []))
            ->map(function (array $item) {
                return [
                    'code' => (string) data_get($item, 'Code'),
                    'name' => (string) data_get($item, 'Name', ''),
                ];
            })
            ->sortBy('code')
            ->values();

        return response()->json($items);
    }
}
