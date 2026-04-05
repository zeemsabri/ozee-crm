<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientVaultCredential;
use App\Services\VaultService;
use Illuminate\Http\Request;
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
        $credentials = ClientVaultCredential::where('client_id', $client->id)
            ->where('expires_at', '>', now())
            ->get(['id', 'label', 'expires_at', 'last_viewed_at', 'created_at']);

        return response()->json($credentials);
    }

    /**
     * Unlock a specific credential using the provided PIN.
     */
    public function unlock(Request $request)
    {
        $request->validate([
            'credential_id' => 'required|uuid|exists:client_vault_credentials,id',
            'pin' => 'required|string',
        ]);

        $key = 'vault_unlock_attempts:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'message' => 'Too many unlock attempts. Please try again in a minute.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        $credential = ClientVaultCredential::findOrFail($request->credential_id);

        // 1. Attempt decryption
        $username = $this->vaultService->decrypt($credential->encrypted_username, $request->pin, $credential->salt);
        $password = $this->vaultService->decrypt($credential->encrypted_password, $request->pin, $credential->salt);

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

        return response()->json([
            'username' => $username,
            'password' => $password,
        ]);
    }

    /**
     * Fetch activity logs for a specific credential.
     */
    public function logs(ClientVaultCredential $credential)
    {
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
        // Add auth check if needed
        $credential->delete();
        return response()->json(['success' => true]);
    }
}
