<?php

namespace App\Http\Middleware;

use App\Services\PortalSessionService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Gate for the supplier portal.
 *
 * Resolves whoever is using the portal — a signed-in team member, or someone holding a
 * verified emailed-code session — and hands them to the controllers on the request.
 * Unidentified visitors are sent back to the front door rather than shown a 403, since
 * the usual reason to be here without a session is an expired one.
 */
class EnsurePortalUser
{
    public function __construct(private PortalSessionService $portalSession) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $this->portalSession->resolve($request);

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Verify your email again to continue.',
                ], 401);
            }

            // An Inertia visit from a React portal page cannot follow an ordinary
            // redirect to '/', because that renders a Vue page and the React runtime
            // has no component for it. Inertia::location returns a 409 telling the
            // client to do a full page load instead.
            if ($request->header('X-Inertia')) {
                return Inertia::location('/');
            }

            return redirect('/')->with(
                'error',
                'That link has expired. Open the project link from your email to sign in again.'
            );
        }

        // Controllers read this rather than re-resolving; `portal_user` avoids any
        // collision with Laravel's own `user` resolver on the request.
        $request->attributes->set('portal_user', $user);

        return $next($request);
    }
}
