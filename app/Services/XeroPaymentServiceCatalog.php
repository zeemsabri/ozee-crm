<?php

namespace App\Services;

use App\Models\XeroConnection;
use App\Models\XeroPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class XeroPaymentServiceCatalog
{
    public function __construct(
        private readonly XeroTokenService $xeroTokenService,
        private readonly XeroAuthService $xeroAuthService
    )
    {
    }

    public function listCached(bool $refreshIfStale = true): array
    {
        $connection = $this->xeroTokenService->getActiveConnection();

        if ($refreshIfStale && $this->shouldRefreshCache($connection->id)) {
            try {
                return $this->syncCache($connection);
            } catch (\Throwable $e) {
                Log::warning('Xero payment service refresh failed; falling back to cache.', [
                    'error' => $e->getMessage(),
                    'xero_connection_id' => $connection->id,
                ]);
            }
        }

        $services = XeroPaymentService::query()
            ->where('xero_connection_id', $connection->id)
            ->where('status', 'ACTIVE')
            ->orderByRaw('COALESCE(name, payment_service_id) asc')
            ->get();

        return $this->formatForUi($services);
    }

    public function syncCache(?XeroConnection $connection = null): array
    {
        $connection ??= $this->xeroTokenService->getActiveConnection();

        try {
            $response = $this->fetchRemotePaymentServices($connection);
        } catch (RuntimeException $exception) {
            if ($this->isPaymentServicesUnavailable($exception)) {
                Log::warning('Xero PaymentServices endpoint is unavailable for this app; returning cached services.', [
                    'xero_connection_id' => $connection->id,
                    'error' => $exception->getMessage(),
                ]);

                return $this->getCachedActiveServices($connection->id);
            }

            throw $exception;
        }

        $now = now();
        $normalized = collect(data_get($response, 'PaymentServices', []))
            ->filter(fn ($service) => is_array($service))
            ->map(fn (array $service) => $this->normalizeService($service, $now))
            ->filter(fn (array $service) => $service['payment_service_id'] !== '')
            ->values();

        DB::transaction(function () use ($connection, $normalized): void {
            foreach ($normalized as $service) {
                XeroPaymentService::query()->updateOrCreate(
                    [
                        'xero_connection_id' => $connection->id,
                        'payment_service_id' => $service['payment_service_id'],
                    ],
                    [
                        'name' => $service['name'],
                        'slug' => $service['slug'],
                        'status' => $service['status'],
                        'provider' => $service['provider'],
                        'raw_payload' => $service['raw_payload'],
                        'last_synced_at' => $service['last_synced_at'],
                    ]
                );
            }

            $knownIds = $normalized->pluck('payment_service_id')->all();
            $staleRows = XeroPaymentService::query()
                ->where('xero_connection_id', $connection->id);

            if (!empty($knownIds)) {
                $staleRows->whereNotIn('payment_service_id', $knownIds);
            }

            $staleRows->update([
                'status' => 'INACTIVE',
                'last_synced_at' => $now,
            ]);
        });

        return $this->getCachedActiveServices($connection->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRemotePaymentServices(XeroConnection $connection): array
    {
        $request = function (XeroConnection $activeConnection) {
            return Http::withToken($activeConnection->access_token)
                ->withHeaders([
                    'Xero-tenant-id' => $activeConnection->selected_tenant_id,
                    'Accept' => 'application/json',
                ])
                ->get('https://api.xero.com/api.xro/2.0/PaymentServices');
        };

        $response = $request($connection);

        if ($response->status() === 401) {
            $connection = $this->xeroAuthService->refreshConnection($connection->fresh(['tenants']) ?? $connection);
            $response = $request($connection);
        }

        if (in_array($response->status(), [401, 403], true)) {
            $detail = (string) data_get($response->json(), 'Detail', '');
            $title = (string) data_get($response->json(), 'Title', '');

            if (
                Str::contains($detail, 'AuthorizationUnsuccessful', true)
                || Str::contains($title, 'Unauthorized', true)
            ) {
                throw new RuntimeException(
                    'Xero PaymentServices API is unavailable for this app. This endpoint requires Xero payment-service partner certification.'
                );
            }
        }

        return $response->throw()->json();
    }

    private function isPaymentServicesUnavailable(RuntimeException $exception): bool
    {
        return Str::contains($exception->getMessage(), 'PaymentServices API is unavailable for this app', true);
    }

    /**
     * @return array<int, array{id: string, name: string, status: string, provider: string|null, last_synced_at: string|null}>
     */
    private function getCachedActiveServices(int $connectionId): array
    {
        $activeServices = XeroPaymentService::query()
            ->where('xero_connection_id', $connectionId)
            ->where('status', 'ACTIVE')
            ->orderByRaw('COALESCE(name, payment_service_id) asc')
            ->get();

        return $this->formatForUi($activeServices);
    }

    private function shouldRefreshCache(int $connectionId): bool
    {
        $latestSyncedAt = XeroPaymentService::query()
            ->where('xero_connection_id', $connectionId)
            ->max('last_synced_at');

        if (!$latestSyncedAt) {
            return true;
        }

        return Carbon::parse($latestSyncedAt)->lt(now()->subHours(6));
    }

    private function normalizeService(array $service, Carbon $syncedAt): array
    {
        $id = trim((string) data_get($service, 'PaymentServiceID', ''));
        $name = trim((string) (data_get($service, 'Name') ?? data_get($service, 'PaymentServiceName') ?? ''));
        $provider = trim((string) (data_get($service, 'Type') ?? data_get($service, 'ServiceType') ?? ''));

        return [
            'payment_service_id' => $id,
            'name' => $name === '' ? null : $name,
            'slug' => $name === '' ? null : Str::slug($name),
            'status' => strtoupper((string) data_get($service, 'Status', 'ACTIVE')),
            'provider' => $provider === '' ? null : $provider,
            'raw_payload' => $service,
            'last_synced_at' => $syncedAt,
        ];
    }

    /**
     * @param Collection<int, XeroPaymentService> $services
     * @return array<int, array{id: string, name: string, status: string, provider: string|null, last_synced_at: string|null}>
     */
    private function formatForUi(Collection $services): array
    {
        return $services->map(function (XeroPaymentService $service) {
            return [
                'id' => $service->payment_service_id,
                'name' => $service->name ?: $service->payment_service_id,
                'status' => $service->status,
                'provider' => $service->provider,
                'last_synced_at' => $service->last_synced_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
