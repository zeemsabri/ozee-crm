<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientVaultCredential;
use App\Models\MagicLink;
use App\Models\Client;
use App\Services\VaultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class VaultController extends Controller
{
    protected $vaultService;

    public function __construct(VaultService $vaultService)
    {
        $this->vaultService = $vaultService;
    }

    public function index(Request $request)
    {
        $client = $this->getClientFromToken($request->query('token'));
        if (!$client) return response()->json(['message' => 'Unauthorized'], 403);

        $credentials = ClientVaultCredential::where('client_id', $client->id)
            ->where(function ($query) {
                $query->where('source', ClientVaultCredential::SOURCE_CLIENT)
                    ->orWhere(function ($teamQuery) {
                        $teamQuery->where('source', ClientVaultCredential::SOURCE_TEAM)
                            ->where('is_visible_to_client', true);
                    });
            })
            ->notExpired()
            ->latest()
            ->get(['id', 'label', 'expires_at', 'last_viewed_at', 'created_at']);

        return response()->json($credentials);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'label' => 'required|string|max:255',
            'username' => 'required|string',
            'password' => 'required|string',
            'pin' => 'required|string|min:4|max:6',
            'expiry_days' => 'required|integer|in:1,3,7,30',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $client = $this->getClientFromToken($request->token);
        if (!$client) return response()->json(['message' => 'Unauthorized'], 403);

        $salt = $this->vaultService->generateSalt();
        $encryptedUsername = $this->vaultService->encrypt($request->username, $request->pin, $salt);
        $encryptedPassword = $this->vaultService->encrypt($request->password, $request->pin, $salt);

        $credential = ClientVaultCredential::create([
            'client_id' => $client->id,
            'source' => ClientVaultCredential::SOURCE_CLIENT,
            'is_visible_to_client' => true,
            'label' => $request->label,
            'encrypted_username' => $encryptedUsername,
            'encrypted_password' => $encryptedPassword,
            'salt' => $salt,
            'expires_at' => Carbon::now()->addDays($request->expiry_days),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Credential securely stored.',
            'credential_id' => $credential->id,
        ]);
    }

    public function logs(Request $request, $id)
    {
        $client = $this->getClientFromToken($request->query('token'));
        if (!$client) return response()->json(['message' => 'Unauthorized'], 403);

        $credential = ClientVaultCredential::where('client_id', $client->id)
            ->where('source', ClientVaultCredential::SOURCE_CLIENT)
            ->findOrFail($id);

        $activities = \Spatie\Activitylog\Models\Activity::forSubject($credential)
            ->with('causer')
            ->latest()
            ->get();

        return response()->json($activities);
    }

    public function destroy(Request $request, $id)
    {
        $client = $this->getClientFromToken($request->query('token'));
        if (!$client) return response()->json(['message' => 'Unauthorized'], 403);

        $credential = ClientVaultCredential::where('client_id', $client->id)
            ->where('source', ClientVaultCredential::SOURCE_CLIENT)
            ->findOrFail($id);
        $credential->forceDelete();

        return response()->json(['success' => true]);
    }

    private function getClientFromToken($token)
    {
        if (!$token) return null;
        $magicLink = MagicLink::where('token', $token)->first();
        if (!$magicLink || $magicLink->hasExpired()) return null;
        return Client::where('email', $magicLink->email)->first();
    }
}
