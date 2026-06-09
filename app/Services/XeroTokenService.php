<?php

namespace App\Services;

use App\Models\XeroConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class XeroTokenService
{
    public function __construct(private readonly XeroAuthService $xeroAuthService) {}

    public function getActiveConnection(): XeroConnection
    {
        /** @var XeroConnection|null $connection */
        $connection = XeroConnection::query()
            ->with('tenants')
            ->where('provider', XeroConnection::PROVIDER)
            ->first();

        if (! $connection || ! $connection->refresh_token) {
            throw new RuntimeException('Xero is not connected.');
        }

        return Cache::lock('xero-token-refresh', 10)->block(5, function () use ($connection) {
            $freshConnection = $connection->fresh(['tenants']) ?? $connection;

            if ($freshConnection->needsRefresh()) {
                try {
                    $freshConnection = $this->xeroAuthService->refreshConnection($freshConnection);
                } catch (Throwable $throwable) {
                    throw new RuntimeException('Xero token refresh failed. A super admin must reconnect Xero.', previous: $throwable);
                }
            }

            if (! $freshConnection->selected_tenant_id) {
                throw new RuntimeException('A Xero tenant must be selected before making API calls.');
            }

            $freshConnection = $this->ensureUsableTenantSelection($freshConnection);

            return $freshConnection;
        });
    }

    private function ensureUsableTenantSelection(XeroConnection $connection): XeroConnection
    {
        $selectedTenantId = (string) $connection->selected_tenant_id;

        if ($selectedTenantId !== '' && $this->canAccessTenant($connection->access_token, $selectedTenantId)) {
            return $connection;
        }

        $tenantIds = $connection->tenants()
            ->orderByDesc('is_selected')
            ->pluck('tenant_id')
            ->filter()
            ->unique()
            ->values();

        foreach ($tenantIds as $tenantId) {
            $tenantId = (string) $tenantId;
            if ($tenantId === '' || $tenantId === $selectedTenantId) {
                continue;
            }

            if (! $this->canAccessTenant($connection->access_token, $tenantId)) {
                continue;
            }

            $tenant = $connection->tenants()->where('tenant_id', $tenantId)->first();

            $connection->tenants()->update(['is_selected' => false]);
            if ($tenant) {
                $tenant->forceFill(['is_selected' => true])->save();
            }

            $connection->forceFill([
                'selected_tenant_id' => $tenantId,
                'selected_tenant_name' => $tenant?->tenant_name,
                'status' => 'connected',
                'last_error' => null,
            ])->save();

            return $connection->fresh(['tenants']) ?? $connection;
        }

        throw new RuntimeException('No accessible Xero tenant found for accounting API calls. Please reconnect and reselect tenant.');
    }

    private function canAccessTenant(?string $accessToken, string $tenantId): bool
    {
        if (! filled($accessToken) || ! filled($tenantId)) {
            return false;
        }

        $cacheKey = 'xero-tenant-access:'.md5($tenantId.'|'.$accessToken);

        return Cache::remember($cacheKey, 300, function () use ($accessToken, $tenantId): bool {
            try {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->withHeaders([
                        'Xero-tenant-id' => $tenantId,
                    ])
                    ->get('https://api.xero.com/api.xro/2.0/Organisation');

                return $response->successful();
            } catch (Throwable) {
                return false;
            }
        });
    }

    /**
     * @return array{access_token:string, tenant_id:string, tenant_name:?string}
     */
    public function getRuntimeCredentials(): array
    {
        $connection = $this->getActiveConnection();

        return [
            'access_token' => $connection->access_token,
            'tenant_id' => $connection->selected_tenant_id,
            'tenant_name' => $connection->selected_tenant_name,
        ];
    }
}