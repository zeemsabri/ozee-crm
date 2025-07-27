<?php

namespace App\Http\Middleware;

use App\Models\MagicLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMagicLinkToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken(); // Assuming token is in Authorization: Bearer header

        if (!$token) {
            return response()->json(['message' => 'Magic link token not provided.'], 401);
        }

        $magicLink = MagicLink::where('token', $token)->first();

        if (!$magicLink) {
            return response()->json(['message' => 'Invalid magic link token.'], 403);
        }

        if ($magicLink->hasExpired()) {
            return response()->json(['message' => 'Magic link token has expired.'], 403);
        }

        if ($magicLink->hasBeenUsed()) {
            // Option 1: Allow re-use for dashboard view, but disallow actions.
            // For now, let's keep it simple and just block if it's marked as used.
            // If you want to allow multiple views but single action, you'd need more granular logic.
            return response()->json(['message' => 'Magic link token has already been used.'], 403);
        }

        // You can attach the project or magicLink object to the request for controllers to use
        $request->attributes->set('magic_link_project_id', $magicLink->project_id);
        // You might also want to pass the email or a client identifier from the magic link
        $request->attributes->set('magic_link_email', $magicLink->email);

        return $next($request);
    }
}

