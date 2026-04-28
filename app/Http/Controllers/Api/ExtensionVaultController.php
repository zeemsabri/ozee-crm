<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientVaultCredential;
use App\Models\User;
use App\Services\VaultService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class ExtensionVaultController extends Controller
{
    public function __construct(protected VaultService $vaultService) {}

    /**
     * Return all credential labels the authenticated API-key user can access.
     */
    public function labels()
    {
        $user = Auth::user();

        $credentials = $this->accessibleCredentialsQuery($user)
            ->with(['project:id,name', 'client:id,name'])
            ->get(['id', 'label', 'project_id', 'client_id', 'source', 'encrypted_pin', 'updated_at']);

        $items = $credentials->map(function (ClientVaultCredential $credential) {
            return [
                'id' => $credential->id,
                'label' => $credential->label,
                'hostname' => $this->extractHostname($credential->label),
                'project_id' => $credential->project_id,
                'project_name' => $credential->project?->name,
                'client_id' => $credential->client_id,
                'client_name' => $credential->client?->name,
                'source' => $credential->source,
                'requires_pin' => empty($credential->encrypted_pin),
                'updated_at' => optional($credential->updated_at)?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * Resolve one accessible credential for browser autofill.
     */
    public function resolve(Request $request)
    {
        $validated = $request->validate([
            'credential_id' => 'nullable|uuid',
            'label' => 'nullable|string',
            'url' => 'nullable|string',
            'pin' => 'nullable|string',
        ]);

        if (empty($validated['credential_id']) && empty($validated['label']) && empty($validated['url'])) {
            return response()->json([
                'message' => 'Provide credential_id, label, or url.',
            ], 422);
        }

        $user = Auth::user();
        $matches = $this->findMatchingCredentials($validated, $user);

        if ($matches->isEmpty()) {
            return response()->json([
                'message' => 'No matching accessible credential found.',
            ], 404);
        }

        if ($matches->count() > 1 && empty($validated['credential_id'])) {
            return response()->json([
                'message' => 'Multiple credentials match this website. Please select credential_id.',
                'requires_selection' => true,
                'candidates' => $this->mapCandidates($matches),
            ], 409);
        }

        $credential = $matches->first();

        $pin = $validated['pin'] ?? $this->resolveStoredPin($credential, $user);

        if (! $pin) {
            return response()->json([
                'message' => 'This credential requires a PIN to unlock.',
                'requires_pin' => true,
                'credential_id' => $credential->id,
                'label' => $credential->label,
            ], 422);
        }

        $username = $this->vaultService->decrypt($credential->encrypted_username, $pin, $credential->salt);
        $password = $this->vaultService->decrypt($credential->encrypted_password, $pin, $credential->salt);

        if ($username === null || $password === null) {
            return response()->json([
                'message' => 'Unable to decrypt credential with the provided PIN.',
            ], 422);
        }

        $response = [
            'id' => $credential->id,
            'label' => $credential->label,
            'hostname' => $this->extractHostname($credential->label),
            'project_id' => $credential->project_id,
            'project_name' => $credential->project?->name,
            'client_id' => $credential->client_id,
            'client_name' => $credential->client?->name,
            'username' => $username,
            'password' => $password,
        ];

        if ($this->canRevealPin($credential, $user)) {
            $response['pin'] = $this->resolveStoredPin($credential, $user) ?? ($validated['pin'] ?? null);
        }

        activity()
            ->performedOn($credential)
            ->causedBy($user)
            ->log('Credential resolved through extension API.');

        $credential->update(['last_viewed_at' => now()]);

        return response()->json($response);
    }

    private function accessibleCredentialsQuery(User $user)
    {
        $query = ClientVaultCredential::query()
            ->notExpired()
            ->latest();

        if (! $this->canViewAllCredentials($user)) {
            $query->where(function ($builder) use ($user) {
                $builder->where('created_by', $user->id)
                    ->orWhereHas('sharedWithUsers', function ($shared) use ($user) {
                        $shared->where('users.id', $user->id);
                    });
            });
        }

        return $query;
    }

    private function findMatchingCredentials(array $validated, User $user): Collection
    {
        $credentialsQuery = $this->accessibleCredentialsQuery($user)
            ->with(['project:id,name', 'client:id,name']);

        if (! empty($validated['credential_id'])) {
            $byId = $credentialsQuery
                ->where('id', $validated['credential_id'])
                ->first();

            return $byId ? collect([$byId]) : collect();
        }

        $credentials = $credentialsQuery
            ->get(['id', 'label', 'encrypted_username', 'encrypted_password', 'encrypted_pin', 'salt', 'project_id', 'client_id', 'source', 'last_viewed_at', 'created_at', 'updated_at']);

        $label = strtolower(trim((string) ($validated['label'] ?? '')));
        $url = trim((string) ($validated['url'] ?? ''));
        $hostname = strtolower((string) $this->extractHostname($url));

        if ($label !== '') {
            $exactLabelMatches = $credentials->filter(function (ClientVaultCredential $credential) use ($label) {
                return strtolower(trim($credential->label)) === $label;
            })->values();

            if ($exactLabelMatches->isNotEmpty()) {
                return $exactLabelMatches;
            }
        }

        if ($hostname !== '') {
            $hostMatches = $credentials->filter(function (ClientVaultCredential $credential) use ($hostname) {
                $credentialHost = strtolower((string) $this->extractHostname($credential->label));

                if ($credentialHost === '') {
                    return false;
                }

                return $credentialHost === $hostname
                    || str_ends_with($hostname, '.'.$credentialHost)
                    || str_ends_with($credentialHost, '.'.$hostname);
            })->values();

            if ($hostMatches->isNotEmpty()) {
                return $hostMatches;
            }
        }

        if ($label !== '') {
            return $credentials->filter(function (ClientVaultCredential $credential) use ($label) {
                return str_contains(strtolower($credential->label), $label);
            })->values();
        }

        return collect();
    }

    private function mapCandidates(Collection $matches): array
    {
        return $matches->map(function (ClientVaultCredential $credential) {
            return [
                'id' => $credential->id,
                'label' => $credential->label,
                'hostname' => $this->extractHostname($credential->label),
                'project_id' => $credential->project_id,
                'project_name' => $credential->project?->name,
                'client_id' => $credential->client_id,
                'client_name' => $credential->client?->name,
                'source' => $credential->source,
                'updated_at' => optional($credential->updated_at)?->toIso8601String(),
            ];
        })->values()->all();
    }

    private function extractHostname(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $candidate = trim($value);
        if ($candidate === '') {
            return null;
        }

        $parsedHost = parse_url($candidate, PHP_URL_HOST);
        if ($parsedHost) {
            return strtolower($parsedHost);
        }

        if (! str_contains($candidate, '://')) {
            $parsedHost = parse_url('https://'.$candidate, PHP_URL_HOST);
            if ($parsedHost) {
                return strtolower($parsedHost);
            }
        }

        return null;
    }

    private function canViewAllCredentials(User $user): bool
    {
        return $user->hasPermission('view_all_credentials');
    }

    private function canRevealPin(ClientVaultCredential $credential, User $user): bool
    {
        if ($this->canViewAllCredentials($user)) {
            return true;
        }

        if ((int) $credential->created_by === (int) $user->id) {
            return true;
        }

        return $credential->sharedWithUsers()->where('users.id', $user->id)->exists();
    }

    private function resolveStoredPin(ClientVaultCredential $credential, User $user): ?string
    {
        if (! $credential->encrypted_pin || ! $this->canRevealPin($credential, $user)) {
            return null;
        }

        try {
            return Crypt::decryptString($credential->encrypted_pin);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
