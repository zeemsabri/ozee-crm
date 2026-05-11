<?php

namespace App\Services;

use App\Models\XeroConnection;
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

            return $freshConnection;
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