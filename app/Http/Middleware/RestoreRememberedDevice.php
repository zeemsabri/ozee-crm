<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\RememberDeviceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * Signs a browser back in from its "Remember me" device cookie.
 *
 * This is the replacement for Laravel's native remember-me, which could only hold one
 * token per user and so logged you out of device A when you signed in on device B.
 * See RememberDeviceService for the storage side.
 *
 * The checks below are not optional politeness — they mirror the gates in
 * AuthenticatedSessionController::store(). Restoring a session is a login, so anything
 * that would have blocked the original login has to block this too, or this middleware
 * becomes the way around them.
 */
class RestoreRememberedDevice
{
    public function __construct(private RememberDeviceService $devices) {}

    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        $device = $this->devices->resolveDevice($request);

        if (! $device) {
            return $next($request);
        }

        $user = User::query()->whereKey($device->user_id)->first();

        if (! $user || ! $this->mayAutoLogin($user)) {
            // The row is unusable — the account is gone, suspended, or of a type that
            // may not hold a web session. Clear it rather than re-checking every
            // request for the next 30 days.
            Cookie::queue($this->devices->forgetDevice($user, $request));

            return $next($request);
        }

        Auth::guard('web')->login($user);

        // A session that changes privilege level gets a fresh id, same as a hand-typed
        // login does. Without this the pre-login session id would carry into the
        // authenticated session, which is the classic fixation hole.
        $request->session()->regenerate();

        $this->devices->touch($device, $request);

        return $next($request);
    }

    /**
     * Every reason the ordinary login flow would refuse this account.
     */
    private function mayAutoLogin(User $user): bool
    {
        // EnsureNotGuest force-logs-out guests on the web guard, and suppliers are
        // rejected outright in store(). Logging either in here just to have them
        // thrown out a moment later is churn at best.
        if (in_array($user->user_type, ['guest', 'supplier'], true)) {
            return false;
        }

        // store() blocks anyone whose account requires the Chrome extension while they
        // are offline, unless they hold the bypass permission. That check exists to
        // keep activity tracked; a remembered device must not be a way around it.
        if ($user->extension_mandatory && ! $user->is_online && ! $user->hasPermission('by_pass_extension')) {
            return false;
        }

        return true;
    }
}
