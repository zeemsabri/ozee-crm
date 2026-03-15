<?php

namespace App\Http\Middleware;

use App\Models\MagicLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyExternalMagicLink
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Magic-Token') ?: $request->bearerToken() ?: $request->query('token');

        if (!$token) {
            return response()->json(['message' => 'Magic token not provided.'], 401);
        }

        $magicLink = MagicLink::where('token', $token)->first();

        if (!$magicLink) {
            return response()->json(['message' => 'Invalid magic token.'], 403);
        }

        if ($magicLink->hasExpired()) {
            return response()->json(['message' => 'Magic token has expired.'], 403);
        }

        if ($magicLink->max_uses !== null && $magicLink->uses_count >= $magicLink->max_uses) {
            return response()->json(['message' => 'Magic token usage limit reached.'], 403);
        }

        // Whitelist check
        if ($magicLink->whitelist && (isset($magicLink->whitelist['domains']) || isset($magicLink->whitelist['ips']))) {
            $clientIp = $request->ip();
            $referrer = $request->header('referer');
            $origin = $request->header('origin');

            $whitelistedDomains = $magicLink->whitelist['domains'] ?? [];
            $whitelistedIps = $magicLink->whitelist['ips'] ?? [];

            $isWhitelisted = false;

            // Check IP
            if (in_array($clientIp, $whitelistedIps)) {
                $isWhitelisted = true;
            }

            // Check Domain (Referrer or Origin)
            if (!$isWhitelisted && !empty($whitelistedDomains)) {
                foreach ($whitelistedDomains as $domain) {
                    if ($origin && str_contains($origin, $domain)) {
                        $isWhitelisted = true;
                        break;
                    }
                    if ($referrer && str_contains($referrer, $domain)) {
                        $isWhitelisted = true;
                        break;
                    }
                }
            }

            if (!$isWhitelisted && (!empty($whitelistedIps) || !empty($whitelistedDomains))) {
                return response()->json(['message' => 'Unauthorized source.'], 403);
            }
        }

        // Increment uses_count and update last_used_at
        $magicLink->increment('uses_count', 1, [
            'last_used_at' => now(),
        ]);

        // Attach project and magicLink to request
        $request->attributes->set('magic_link_project_id', $magicLink->project_id);
        $request->attributes->set('magic_link_email', $magicLink->email);
        $request->attributes->set('magic_link', $magicLink);

        return $next($request);
    }
}
