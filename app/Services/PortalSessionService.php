<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Identity for the supplier portal.
 *
 * Hybrid by design. Some people reaching the portal are outside suppliers who only
 * ever prove who they are with an emailed code; others are real team members who
 * already have an account. Both end up as the same `User`, so the rest of the portal
 * never has to care which one it is dealing with:
 *
 *   1. The portal cookie is read first, and the OTP session behind it resolved. This
 *      is the identity the visitor deliberately established for the portal in this
 *      browser, so it wins — otherwise a staff member who verified a supplier's code
 *      would silently be treated as themselves and 404 on the supplier's project.
 *   2. Failing that, someone already signed into the main app (and not a `guest`) is
 *      taken at face value — no code needed.
 *
 * The session token lives in an HttpOnly cookie rather than localStorage, so page
 * requests carry it and the portal can serve real server-rendered URLs.
 *
 * Note this deliberately does NOT log anyone into Laravel's `web` guard.
 * `App\Http\Middleware\EnsureNotGuest` sits on the whole web stack and force-logs-out
 * any authenticated user whose `user_type` is `guest` — the portal must not fight that.
 */
class PortalSessionService
{
    public const COOKIE = 'portal_session';

    /** How long a verified portal session stays usable, measured from `verified_at`. */
    public const LIFETIME_DAYS = 30;

    /**
     * Who is using the portal right now, or null if nobody is.
     */
    public function resolve(Request $request): ?User
    {
        $token = $this->tokenFrom($request);

        if ($token && $user = $this->resolveByToken($token)) {
            return $user;
        }

        // A signed-in team member is already identified; EnsureNotGuest guarantees this
        // is never a `guest`-type account.
        if (Auth::check() && Auth::user()->user_type !== 'guest') {
            return Auth::user();
        }

        return null;
    }

    /**
     * How the current visitor was identified: `session` for the emailed-code cookie,
     * `login` for the main app's own session, null for nobody.
     *
     * The pages use this to decide whether a "Sign out" button would actually do
     * anything — clearing the portal cookie does nothing for someone identified by
     * their ordinary app login.
     */
    public function resolvedVia(Request $request): ?string
    {
        $token = $this->tokenFrom($request);

        if ($token && $this->resolveByToken($token)) {
            return 'session';
        }

        if (Auth::check() && Auth::user()->user_type !== 'guest') {
            return 'login';
        }

        return null;
    }

    /**
     * Resolve a portal session token to its user, honouring the session lifetime.
     */
    public function resolveByToken(string $sessionToken): ?User
    {
        $record = OtpVerification::query()
            ->where('session_token', $sessionToken)
            ->whereNotNull('verified_at')
            ->whereNotNull('guest_user_id')
            ->latest('verified_at')
            ->first();

        if (! $record || $this->sessionExpired($record)) {
            return null;
        }

        // Deliberately NOT withTrashed: soft-deleting someone must end their portal
        // access immediately. Their project_user rows survive a soft delete, so a
        // trashed team member would otherwise keep reading every project they were on
        // until the session aged out.
        return User::query()->whereKey($record->guest_user_id)->first();
    }

    /**
     * A verified session is good for LIFETIME_DAYS from the moment it was verified.
     *
     * Derived from `verified_at` rather than stored, so this needed no new column —
     * `expires_at` on the same row is the OTP's own 10-minute window and must not be
     * reused for it.
     */
    public function sessionExpired(OtpVerification $record): bool
    {
        if (! $record->verified_at) {
            return true;
        }

        return Carbon::parse($record->verified_at)->addDays(self::LIFETIME_DAYS)->isPast();
    }

    /**
     * The cookie to attach to a response after a successful verification.
     */
    public function cookieFor(string $sessionToken): SymfonyCookie
    {
        return Cookie::make(
            name: self::COOKIE,
            value: $sessionToken,
            minutes: self::LIFETIME_DAYS * 24 * 60,
            path: '/',
            domain: null,
            secure: null,     // follows session.secure / the current scheme
            httpOnly: true,   // the portal reads this server-side only
            raw: false,
            sameSite: 'lax',  // survives following the emailed link
        );
    }

    public function forgetCookie(): SymfonyCookie
    {
        return Cookie::forget(self::COOKIE, '/');
    }

    /**
     * Invalidate the underlying session record, so signing out is not merely
     * client-side. Anything else sharing the token stops working too, which is the
     * point.
     */
    public function invalidate(?string $sessionToken): void
    {
        if (! $sessionToken) {
            return;
        }

        OtpVerification::where('session_token', $sessionToken)->update(['session_token' => null]);
    }

    /**
     * The session token, from the cookie and nowhere else.
     *
     * Deliberately does not fall back to a request field: `Request::input()` reads the
     * query string, which would turn a 30-day credential into something that lands in
     * browser history, access logs and any URL someone pastes into a chat — and would
     * let one person hand another a working session by link.
     */
    public function tokenFrom(Request $request): ?string
    {
        $cookie = $request->cookie(self::COOKIE);

        return is_string($cookie) && $cookie !== '' ? $cookie : null;
    }
}
