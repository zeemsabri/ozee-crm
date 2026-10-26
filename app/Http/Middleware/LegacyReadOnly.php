<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The legacy CRM is being retired in favour of app.ozeeweb.com.au: staff can
 * view everything but only change invoices. Super admins are unrestricted.
 *
 * Only requests made as a staff user are checked, so external email apps,
 * Stripe/Xero webhooks and the client portal (magic links) are unaffected.
 */
class LegacyReadOnly
{
    private const ALLOWED = [
        'logout',
        'api/logout',
        'api/logout-token',
        'broadcasting/auth',
        'api/notifications*',
        // Invoicing stays here until it works in the new app.
        'api/projects/*/invoices',
        'api/projects/*/invoices/*',
        'api/invoices/*',
        'api/clients/*/xero-contact-*',
        'api/admin/xero/invoices/sync',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe() || $request->is(...self::ALLOWED)) {
            return $next($request);
        }

        $user = Auth::user() ?? Auth::guard('sanctum')->user();

        // The desktop tracker authenticates by API key in route middleware,
        // which runs after this one, so resolve its user here.
        if (! $user && $request->is('api/activity/tasks*')) {
            $apiKey = $request->header('X-API-KEY') ?: $request->query('api_key');
            $user = $apiKey ? User::where('api_key', $apiKey)->first() : null;
        }

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $message = 'This CRM is now read-only. Please use app.ozeeweb.com.au.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message], 403);
        }

        return back()->with('error', $message);
    }
}
