<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientVaultCredential;
use App\Models\Project;
use App\Models\User;
use App\Services\VaultService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;

class VaultController extends Controller
{
    protected $vaultService;

    public function __construct(VaultService $vaultService)
    {
        $this->vaultService = $vaultService;
    }

    /**
     * List only the labels and statuses of credentials for a client.
     */
    public function index(Client $client)
    {
        $user = Auth::user();

        $credentialsQuery = ClientVaultCredential::query()
            ->where('client_id', $client->id)
            ->notExpired()
            ->latest();

        if (! $this->canViewAllCredentials($user)) {
            $credentialsQuery->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('sharedWithUsers', function ($shared) use ($user) {
                        $shared->where('users.id', $user->id);
                    });
            });
        }

        $credentials = $credentialsQuery->get(['id', 'client_id', 'project_id', 'source', 'is_visible_to_client', 'label', 'expires_at', 'last_viewed_at', 'created_at', 'created_by']);

        return response()->json($credentials);
    }

    /**
     * List credentials linked to a project.
     */
    public function indexByProject(Project $project)
    {
        $user = Auth::user();

        $credentialsQuery = ClientVaultCredential::query()
            ->where('project_id', $project->id)
            ->notExpired()
            ->latest();

        if (! $this->canViewAllCredentials($user)) {
            $credentialsQuery->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('sharedWithUsers', function ($shared) use ($user) {
                        $shared->where('users.id', $user->id);
                    });
            });
        }

        $credentials = $credentialsQuery->get(['id', 'client_id', 'project_id', 'source', 'is_visible_to_client', 'label', 'expires_at', 'last_viewed_at', 'created_at', 'created_by']);

        return response()->json($credentials);
    }

    /**
     * Create a team credential on a project.
     */
    public function storeForProject(Request $request, Project $project)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'username' => 'required|string',
            'password' => 'required|string',
            'pin' => 'required|string|min:4|max:10',
            'is_visible_to_client' => 'sometimes|boolean',
            'expiry_days' => 'nullable|integer|min:0|max:3650',
        ]);

        $salt = $this->vaultService->generateSalt();
        $encryptedUsername = $this->vaultService->encrypt($validated['username'], $validated['pin'], $salt);
        $encryptedPassword = $this->vaultService->encrypt($validated['password'], $validated['pin'], $salt);

        $clientId = $project->client_id;

        // Some projects are linked to clients only through the project_client pivot table.
        if (! $clientId) {
            $clientId = $project->clients()->value('clients.id');
        }

        if (! $clientId) {
            return response()->json([
                'message' => 'This project has no linked client. Please attach a client first.',
            ], 422);
        }

        $expiryDays = (int) ($validated['expiry_days'] ?? 0);

        $credential = ClientVaultCredential::create([
            'client_id' => $clientId,
            'project_id' => $project->id,
            'created_by' => Auth::id(),
            'source' => ClientVaultCredential::SOURCE_TEAM,
            'is_visible_to_client' => (bool) ($validated['is_visible_to_client'] ?? false),
            'label' => $validated['label'],
            'encrypted_username' => $encryptedUsername,
            'encrypted_password' => $encryptedPassword,
            'encrypted_pin' => Crypt::encryptString($validated['pin']),
            'salt' => $salt,
            'expires_at' => $expiryDays === 0 ? null : Carbon::now()->addDays($expiryDays),
        ]);

        return response()->json([
            'success' => true,
            'credential_id' => $credential->id,
            'message' => 'Credential stored successfully.',
        ], 201);
    }

    /**
     * Update a credential. Restricted to users with view_all_credentials.
     */
    public function update(Request $request, ClientVaultCredential $credential)
    {
        $user = Auth::user();

        if (! $this->canViewAllCredentials($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'username' => 'sometimes|string',
            'password' => 'sometimes|string',
            'pin' => 'nullable|string|min:4|max:10',
            'is_visible_to_client' => 'sometimes|boolean',
            'expiry_days' => 'nullable|integer|min:0|max:3650',
        ]);

        $updates = [];

        if (array_key_exists('label', $validated)) {
            $updates['label'] = $validated['label'];
        }

        if (array_key_exists('is_visible_to_client', $validated)) {
            $updates['is_visible_to_client'] = (bool) $validated['is_visible_to_client'];
        }

        if (array_key_exists('expiry_days', $validated)) {
            $expiryDays = (int) $validated['expiry_days'];
            $updates['expires_at'] = $expiryDays === 0 ? null : Carbon::now()->addDays($expiryDays);
        }

        $newPin = $validated['pin'] ?? null;
        $username = $validated['username'] ?? null;
        $password = $validated['password'] ?? null;

        if ($newPin !== null) {
            $currentPin = $this->resolvePinForAuthorizedUser($credential, $user);

            if (! $currentPin && ($username === null || $password === null)) {
                return response()->json([
                    'message' => 'Current PIN could not be resolved. Provide username and password along with the new PIN.',
                ], 422);
            }

            // Preserve existing secrets when only PIN rotates.
            if ($username === null) {
                $username = $this->vaultService->decrypt($credential->encrypted_username, $currentPin, $credential->salt);
            }

            if ($password === null) {
                $password = $this->vaultService->decrypt($credential->encrypted_password, $currentPin, $credential->salt);
            }

            if ($username === null || $password === null) {
                return response()->json([
                    'message' => 'Unable to decrypt existing credential values for PIN rotation.',
                ], 422);
            }

            $credential->salt = $this->vaultService->generateSalt();
            $updates['salt'] = $credential->salt;
            $updates['encrypted_pin'] = Crypt::encryptString($newPin);
        } elseif ($username !== null || $password !== null) {
            $newPin = $this->resolvePinForAuthorizedUser($credential, $user);
        }

        if ($username !== null) {
            if (! $newPin) {
                return response()->json(['message' => 'A PIN is required to update username.'], 422);
            }
            $updates['encrypted_username'] = $this->vaultService->encrypt($username, $newPin, $credential->salt);
        }

        if ($password !== null) {
            if (! $newPin) {
                return response()->json(['message' => 'A PIN is required to update password.'], 422);
            }
            $updates['encrypted_password'] = $this->vaultService->encrypt($password, $newPin, $credential->salt);
        }

        if (! empty($updates)) {
            $credential->update($updates);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Share credential access with users. Owner or users with view_all_credentials can share.
     */
    public function share(Request $request, ClientVaultCredential $credential)
    {
        $user = Auth::user();

        if (! $this->canShareCredential($credential, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $shareRows = [];
        foreach (array_unique($validated['user_ids']) as $userId) {
            $shareRows[$userId] = ['granted_by' => $user->id];
        }

        $credential->sharedWithUsers()->syncWithoutDetaching($shareRows);

        return response()->json(['success' => true]);
    }

    /**
     * Revoke a shared user. Restricted to view_all_credentials users.
     */
    public function revoke(ClientVaultCredential $credential, User $user)
    {
        if (! $this->canViewAllCredentials(Auth::user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $credential->sharedWithUsers()->detach($user->id);

        return response()->json(['success' => true]);
    }

    /**
     * List users who can access a credential through explicit sharing.
     */
    public function sharedUsers(ClientVaultCredential $credential)
    {
        $user = Auth::user();

        if (! $this->canShareCredential($credential, $user) && ! $this->canViewAllCredentials($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(
            $credential->sharedWithUsers()
                ->select('users.id', 'users.name', 'users.email')
                ->orderBy('users.name')
                ->get()
        );
    }

    /**
     * Unlock a specific credential using the provided PIN.
     */
    public function unlock(Request $request)
    {
        $request->validate([
            'credential_id' => 'required|uuid|exists:client_vault_credentials,id',
            'pin' => 'nullable|string',
        ]);

        $user = Auth::user();

        $key = 'vault_unlock_attempts:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'message' => 'Too many unlock attempts. Please try again in a minute.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        $credential = ClientVaultCredential::findOrFail($request->credential_id);

        if (! $this->canAccessCredential($credential, $user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $pin = $request->pin;
        if (! $pin && $credential->isTeamSubmitted()) {
            $pin = $this->resolvePinForAuthorizedUser($credential, $user);
        }

        if (! $pin) {
            return response()->json(['message' => 'PIN is required'], 422);
        }

        // 1. Attempt decryption
        $username = $this->vaultService->decrypt($credential->encrypted_username, $pin, $credential->salt);
        $password = $this->vaultService->decrypt($credential->encrypted_password, $pin, $credential->salt);

        if (!$username || !$password) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Invalid PIN'], 401);
        }

        // 2. SUCCESS: Log activity using Spatie
        activity()
            ->performedOn($credential)
            ->causedBy(auth()->user())
            ->log('Credential unlocked and viewed by team member.');

        // 3. Update last viewed at
        $credential->update(['last_viewed_at' => now()]);

        $response = [
            'username' => $username,
            'password' => $password,
        ];

        if ($this->canRevealPin($credential, $user)) {
            $resolvedPin = $this->resolvePinForAuthorizedUser($credential, $user);
            if ($resolvedPin) {
                $response['pin'] = $resolvedPin;
            } elseif (! empty($request->pin)) {
                // Fallback for records without stored encrypted_pin: show the verified PIN used for unlock.
                $response['pin'] = $request->pin;
            }
        }

        return response()->json($response);
    }

    /**
     * Fetch activity logs for a specific credential.
     */
    public function logs(ClientVaultCredential $credential)
    {
        if (! $this->canAccessCredential($credential, Auth::user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $activities = Activity::forSubject($credential)
            ->with('causer')
            ->latest()
            ->get();

        return response()->json($activities);
    }

    /**
     * Delete a credential manually.
     */
    public function destroy(ClientVaultCredential $credential)
    {
        if (! $this->canViewAllCredentials(Auth::user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($credential->isClientSubmitted()) {
            $credential->forceDelete();
        } else {
            $credential->delete();
        }

        return response()->json(['success' => true]);
    }

    private function canViewAllCredentials(User $user): bool
    {
        return $user->hasPermission('view_all_credentials');
    }

    private function canAccessCredential(ClientVaultCredential $credential, User $user): bool
    {
        if ($this->canViewAllCredentials($user)) {
            return true;
        }

        if ((int) $credential->created_by === (int) $user->id) {
            return true;
        }

        return $credential->sharedWithUsers()->where('users.id', $user->id)->exists();
    }

    private function canShareCredential(ClientVaultCredential $credential, User $user): bool
    {
        return $this->canViewAllCredentials($user) || (int) $credential->created_by === (int) $user->id;
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

    private function resolvePinForAuthorizedUser(ClientVaultCredential $credential, User $user): ?string
    {
        if (! $credential->encrypted_pin) {
            return null;
        }

        if (! $this->canRevealPin($credential, $user)) {
            return null;
        }

        try {
            return Crypt::decryptString($credential->encrypted_pin);
        } catch (\Throwable $e) {
            Log::warning('Failed to decrypt vault credential PIN.', [
                'credential_id' => $credential->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
