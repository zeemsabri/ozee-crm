<?php

namespace App\Services;

use App\Models\MagicLink;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class MagicLinkService
{
    /**
     * Generate a temporary signed magic link URL.
     */
    public function generateMagicLink(string $email, int $projectId): string
    {
        // Generate a unique token
        $token = Str::random(64);
        $expiresAt = now()->addHours(24);

        // Create a new magic link in the database
        MagicLink::create([
            'email' => $email,
            'token' => $token,
            'project_id' => $projectId,
            'expires_at' => $expiresAt,
            'used' => false,
        ]);

        return URL::temporarySignedRoute(
            'client.magic-link-login',
            $expiresAt,
            ['token' => $token]
        );
    }

    /**
     * Get an existing, non-expired magic link for a given email and project.
     */
    public function getValidMagicLink(string $email, int $projectId): ?MagicLink
    {
        return MagicLink::where('email', $email)
            ->where('project_id', $projectId)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();
    }
}
