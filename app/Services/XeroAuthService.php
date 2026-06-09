<?php

namespace App\Services;

use App\Models\User;
use App\Models\XeroConnection;
use App\Models\XeroTenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;
use Illuminate\Support\Str;
use RuntimeException;

class XeroAuthService
{
    private const AUTHORIZE_URL = 'https://login.xero.com/identity/connect/authorize';

    private const TOKEN_URL = 'https://identity.xero.com/connect/token';

    private const CONNECTIONS_URL = 'https://api.xero.com/connections';

    public function getAuthorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('xero.clientId'),
            'redirect_uri' => config('xero.redirectUri'),
            'scope' => config('xero.scopes'),
            'state' => $state,
        ]);
    }

    public function beginAuthorization(User $user): string
    {
        if (! $user->isSuperAdmin()) {
            abort(403);
        }

        $state = Str::random(40);

        session([
            'xero_oauth_state' => $state,
            'xero_oauth_user_id' => $user->id,
        ]);

        return $this->getAuthorizationUrl($state);
    }

    public function handleCallback(string $code, string $state): XeroConnection
    {
        $expectedState = (string) session('xero_oauth_state');
        $userId = session('xero_oauth_user_id');

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            abort(403, 'Invalid Xero authorization state.');
        }

        /** @var User|null $user */
        $user = User::find($userId);

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Only a super admin can connect Xero.');
        }

        $tokenData = $this->exchangeAuthorizationCode($code);
        $tenants = $this->fetchTenants($tokenData['access_token']);

        $connection = DB::transaction(function () use ($user, $tokenData, $tenants) {
            $connection = XeroConnection::query()
                ->lockForUpdate()
                ->firstOrNew(['provider' => XeroConnection::PROVIDER]);

            $previouslySelectedTenantId = $connection->selected_tenant_id;

            $connection->fill($this->buildConnectionPayload($user, $tokenData));
            $connection->save();

            $connection->tenants()->delete();

            foreach ($tenants as $tenant) {
                $connection->tenants()->create([
                    'tenant_id' => $tenant['tenantId'],
                    'tenant_name' => $tenant['tenantName'] ?? null,
                    'tenant_type' => $tenant['tenantType'] ?? null,
                    'auth_event_id' => $tenant['authEventId'] ?? null,
                    'connection_id' => $tenant['id'] ?? null,
                    'created_date_utc' => $this->parseDate($tenant['createdDateUtc'] ?? null),
                    'updated_date_utc' => $this->parseDate($tenant['updatedDateUtc'] ?? null),
                    'is_selected' => false,
                ]);
            }

            $selectedTenantId = null;
            $selectedTenantName = null;

            if (count($tenants) === 1) {
                $selectedTenantId = $tenants[0]['tenantId'];
                $selectedTenantName = $tenants[0]['tenantName'] ?? null;
            } elseif ($previouslySelectedTenantId) {
                $selectedTenant = $connection->tenants()->where('tenant_id', $previouslySelectedTenantId)->first();

                if ($selectedTenant) {
                    $selectedTenantId = $selectedTenant->tenant_id;
                    $selectedTenantName = $selectedTenant->tenant_name;
                }
            }

            $connection->tenants()->update(['is_selected' => false]);

            if ($selectedTenantId) {
                $connection->tenants()
                    ->where('tenant_id', $selectedTenantId)
                    ->update(['is_selected' => true]);
            }

            $connection->forceFill([
                'selected_tenant_id' => $selectedTenantId,
                'selected_tenant_name' => $selectedTenantName,
                'status' => $selectedTenantId ? 'connected' : 'pending_tenant_selection',
                'disconnected_at' => null,
                'last_error' => null,
            ])->save();

            return $connection->fresh(['connectedBy', 'tenants']);
        });

        session()->forget(['xero_oauth_state', 'xero_oauth_user_id']);

        return $connection;
    }

    public function selectTenant(string $tenantId): XeroConnection
    {
        return DB::transaction(function () use ($tenantId) {
            $connection = XeroConnection::query()
                ->with('tenants')
                ->lockForUpdate()
                ->where('provider', XeroConnection::PROVIDER)
                ->firstOrFail();

            $tenant = $connection->tenants->firstWhere('tenant_id', $tenantId);

            if (! $tenant) {
                throw new RuntimeException('The selected Xero tenant does not belong to the active connection.');
            }

            if (! $this->canUseTenantForAccounting($connection->access_token, $tenant->tenant_id)) {
                throw new RuntimeException('The selected Xero organization is not accessible for accounting API calls. Please choose another tenant.');
            }

            $connection->tenants()->update(['is_selected' => false]);
            $tenant->forceFill(['is_selected' => true])->save();

            $connection->forceFill([
                'selected_tenant_id' => $tenant->tenant_id,
                'selected_tenant_name' => $tenant->tenant_name,
                'status' => 'connected',
                'last_error' => null,
            ])->save();

            return $connection->fresh(['connectedBy', 'tenants']);
        });
    }

    private function canUseTenantForAccounting(?string $accessToken, string $tenantId): bool
    {
        if (! filled($accessToken) || ! filled($tenantId)) {
            return false;
        }

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
    }

    public function disconnect(): void
    {
        DB::transaction(function () {
            $connection = XeroConnection::query()
                ->lockForUpdate()
                ->where('provider', XeroConnection::PROVIDER)
                ->first();

            if (! $connection) {
                return;
            }

            if ($connection->refresh_token) {
                $this->sendRevocation($connection->refresh_token);
            }

            $connection->tenants()->delete();

            $connection->forceFill([
                'status' => 'disconnected',
                'selected_tenant_id' => null,
                'selected_tenant_name' => null,
                'access_token' => null,
                'refresh_token' => null,
                'id_token' => null,
                'scope' => null,
                'token_type' => null,
                'access_token_expires_at' => null,
                'refresh_token_expires_at' => null,
                'last_refreshed_at' => null,
                'disconnected_at' => now(),
            ])->save();
        });
    }

    private function exchangeAuthorizationCode(string $code): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('xero.redirectUri'),
        ])->json();
    }

    public function refreshConnection(XeroConnection $connection): XeroConnection
    {
        try {
            $tokenData = $this->tokenRequest([
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ])->json();

            $connection->forceFill([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? $connection->refresh_token,
                'id_token' => $tokenData['id_token'] ?? $connection->id_token,
                'scope' => Arr::get($tokenData, 'scope', $connection->scope),
                'token_type' => Arr::get($tokenData, 'token_type', $connection->token_type),
                'access_token_expires_at' => now()->addSeconds((int) Arr::get($tokenData, 'expires_in', 0)),
                'refresh_token_expires_at' => Arr::has($tokenData, 'refresh_token') ? now()->addDays(60) : $connection->refresh_token_expires_at,
                'last_refreshed_at' => now(),
                'last_error' => null,
                'status' => $connection->selected_tenant_id ? 'connected' : 'pending_tenant_selection',
            ])->save();

            return $connection->fresh(['connectedBy', 'tenants']);
        } catch (Throwable $throwable) {
            $connection->forceFill([
                'status' => 'reconnect_required',
                'last_error' => $throwable->getMessage(),
            ])->save();

            throw $throwable;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTenants(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->get(self::CONNECTIONS_URL)
            ->throw()
            ->json();
    }

    private function sendRevocation(string $refreshToken): void
    {
        try {
            Http::withBasicAuth((string) config('xero.clientId'), (string) config('xero.clientSecret'))
                ->asForm()
                ->post('https://identity.xero.com/connect/revocation', [
                    'token' => $refreshToken,
                ]);
        } catch (Throwable) {
            // Local cleanup still needs to happen even if Xero revocation fails.
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function tokenRequest(array $params): Response
    {
        return Http::withBasicAuth((string) config('xero.clientId'), (string) config('xero.clientSecret'))
            ->asForm()
            ->acceptJson()
            ->post(self::TOKEN_URL, $params)
            ->throw();
    }

    /**
     * @param  array<string, mixed>  $tokenData
     * @return array<string, mixed>
     */
    private function buildConnectionPayload(User $user, array $tokenData): array
    {
        return [
            'connected_by_user_id' => $user->id,
            'status' => 'pending_tenant_selection',
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'id_token' => $tokenData['id_token'] ?? null,
            'scope' => Arr::get($tokenData, 'scope'),
            'token_type' => Arr::get($tokenData, 'token_type'),
            'access_token_expires_at' => now()->addSeconds((int) Arr::get($tokenData, 'expires_in', 0)),
            'refresh_token_expires_at' => Arr::has($tokenData, 'refresh_token') ? now()->addDays(60) : null,
            'last_connected_at' => now(),
            'last_refreshed_at' => now(),
            'disconnected_at' => null,
            'last_error' => null,
        ];
    }

    private function parseDate(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}