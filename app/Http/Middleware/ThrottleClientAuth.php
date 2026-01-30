<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleClientAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $email = $request->input('email');
        $path = $request->path();

        // 1. IP-based hit throttling for all client auth paths
        $ipKey = 'client_auth_ip_' . $ip;
        if (RateLimiter::tooManyAttempts($ipKey, 20)) { // 20 requests per minute per IP
            return response()->json([
                'message' => 'Too many requests. Please slow down.',
                'lockout_seconds' => RateLimiter::availableIn($ipKey),
            ], 429);
        }
        RateLimiter::hit($ipKey, 60);

        // 2. Email-based throttling for magic links
        if (str_contains($path, 'resend-magic-link') || str_contains($path, 'client-magic-link')) {
            if ($email) {
                $emailKey = 'client_magic_link_email_' . $email;
                if (RateLimiter::tooManyAttempts($emailKey, 10)) { // 3 requests per hour
                    return response()->json([
                        'message' => 'Too many magic link requests. Please check your inbox or try again in an hour.',
                        'lockout_seconds' => RateLimiter::availableIn($emailKey),
                    ], 429);
                }
                RateLimiter::hit($emailKey, 3600);
            }
        }

        return $next($request);
    }
}
