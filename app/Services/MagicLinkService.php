<?php

namespace App\Services;

use App\Models\MagicLink;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class MagicLinkService
{
    /**
     * Generate a temporary signed magic link URL.
     *
     * @param string $email
     * @param int $projectId
     * @return string
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
}
