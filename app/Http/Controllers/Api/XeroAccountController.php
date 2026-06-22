<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\XeroTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class XeroAccountController extends Controller
{
    public function __construct(private readonly XeroTokenService $xeroTokenService)
    {
    }

    public function index(Request $request): JsonResponse
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

        $category = $request->query('category');
        if ($category === 'revenue') {
            $allowedTypes = ['REVENUE', 'SALES', 'OTHERINCOME'];
        } elseif ($category === 'expense') {
            $allowedTypes = ['EXPENSE', 'OVERHEADS', 'DIRECTCOSTS'];
        } else {
            $allowedTypes = ['EXPENSE', 'OVERHEADS', 'DIRECTCOSTS'];
        }

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

    public function storeAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
            'type' => 'nullable|in:EXPENSE,OVERHEADS,DIRECTCOSTS,REVENUE,SALES,OTHERINCOME',
        ]);

        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        $accountsResponse = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->get('https://api.xero.com/api.xro/2.0/Accounts')
            ->throw()
            ->json();

        $existingCodes = collect(data_get($accountsResponse, 'Accounts', []))
            ->pluck('Code')
            ->filter()
            ->map(fn ($code) => strtoupper((string) $code))
            ->values()
            ->all();

        $code = $this->resolveUniqueCode(
            $validated['code'] ?? null,
            $validated['name'],
            $existingCodes,
            'ACC',
            10
        );

        $payload = [
            'Accounts' => [[
                'Name' => $validated['name'],
                'Code' => $code,
                'Type' => $validated['type'] ?? 'OVERHEADS',
                'Status' => 'ACTIVE',
            ]],
        ];

        $created = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->post('https://api.xero.com/api.xro/2.0/Accounts', $payload)
            ->throw()
            ->json();

        $account = data_get($created, 'Accounts.0');
        if (!is_array($account)) {
            return response()->json([
                'message' => 'Xero account was created but the response payload was invalid.',
            ], 500);
        }

        return response()->json([
            'code' => strtoupper((string) data_get($account, 'Code', $code)),
            'name' => (string) data_get($account, 'Name', $validated['name']),
            'type' => (string) data_get($account, 'Type', $validated['type'] ?? 'OVERHEADS'),
        ], 201);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:40',
        ]);

        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        $itemsResponse = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->get('https://api.xero.com/api.xro/2.0/Items')
            ->throw()
            ->json();

        $existingCodes = collect(data_get($itemsResponse, 'Items', []))
            ->pluck('Code')
            ->filter()
            ->map(fn ($code) => strtoupper((string) $code))
            ->values()
            ->all();

        $code = $this->resolveUniqueCode(
            $validated['code'] ?? null,
            $validated['name'],
            $existingCodes,
            'ITEM',
            30
        );

        $payload = [
            'Items' => [[
                'Code' => $code,
                'Name' => $validated['name'],
            ]],
        ];

        $created = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->post('https://api.xero.com/api.xro/2.0/Items', $payload)
            ->throw()
            ->json();

        $item = data_get($created, 'Items.0');
        if (!is_array($item)) {
            return response()->json([
                'message' => 'Xero item was created but the response payload was invalid.',
            ], 500);
        }

        return response()->json([
            'code' => strtoupper((string) data_get($item, 'Code', $code)),
            'name' => (string) data_get($item, 'Name', $validated['name']),
        ], 201);
    }

    private function resolveUniqueCode(?string $preferredCode, string $name, array $existingCodes, string $prefix, int $maxLength): string
    {
        $existing = collect($existingCodes)->map(fn ($code) => strtoupper((string) $code));

        $normalize = function (?string $value, string $fallbackPrefix, int $length) use ($name): string {
            $raw = strtoupper((string) $value);
            $raw = preg_replace('/[^A-Z0-9]/', '', $raw ?? '') ?: '';

            if ($raw === '') {
                $slug = strtoupper(Str::of($name)->replaceMatches('/[^A-Za-z0-9]+/', '')->toString());
                $raw = $slug !== '' ? $slug : $fallbackPrefix;
            }

            return substr($raw, 0, $length);
        };

        $base = $normalize($preferredCode, $prefix, $maxLength);
        if (!$existing->contains($base)) {
            return $base;
        }

        $trimmedBase = substr($base, 0, max(1, $maxLength - 3));
        for ($i = 1; $i <= 999; $i++) {
            $candidate = substr($trimmedBase . str_pad((string) $i, 3, '0', STR_PAD_LEFT), 0, $maxLength);
            if (!$existing->contains($candidate)) {
                return $candidate;
            }
        }

        return substr(strtoupper($prefix) . strtoupper(Str::random(6)), 0, $maxLength);
    }
}
