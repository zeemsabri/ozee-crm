<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRememberedDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * "Remember this device" — one row per device, not one token per user.
 *
 * This does two jobs, and it is worth being clear that the second one raises the
 * stakes of the first:
 *
 *   1. Skip the emailed OTP step on a device that has already passed it.
 *   2. Restore the session on a device that ticked "Remember me", so signing in
 *      somewhere else never signs you out here.
 *
 * Job 2 replaces Laravel's native remember-me. That mechanism keeps a single
 * `users.remember_token`, so a second device logging in rewrites the column and
 * silently invalidates the first — the "only one device" behaviour we were asked to
 * fix. Here each device gets its own row, its own token and its own expiry, so there
 * is no shared value to clobber.
 *
 * The cookie holds a 64-character random token; only its SHA-256 hash is stored, so
 * a database leak does not hand anyone a working device. It is HttpOnly and SameSite
 * Lax. It is NOT rotated on each use: two tabs racing a rotation would knock each
 * other out, and Laravel's own remember-me does not rotate either.
 *
 * @see \App\Http\Middleware\RestoreRememberedDevice — the auto-login half
 */
class RememberDeviceService
{
    public const COOKIE_NAME = 'remember_device';

    /**
     * Kept only so older call sites that referenced the constant still resolve.
     *
     * @deprecated Use lifetimeDays(); the value is configurable now.
     */
    public const EXPIRATION_DAYS = 7;

    /**
     * How long a device stays remembered, in days.
     */
    public function lifetimeDays(): int
    {
        return max(1, (int) config('auth.remembered_devices.days', 30));
    }

    /**
     * Whether each use pushes the expiry out again.
     */
    public function isSliding(): bool
    {
        return (bool) config('auth.remembered_devices.sliding', true);
    }

    /**
     * Most devices one account may remember at once. Oldest are dropped past this.
     */
    public function maxPerUser(): int
    {
        return max(1, (int) config('auth.remembered_devices.max_per_user', 10));
    }

    /**
     * The live device row behind this request's cookie, or null.
     *
     * Not scoped to a user on purpose: the auto-login middleware has no user yet and
     * has to resolve one *from* the row.
     */
    public function resolveDevice(Request $request): ?UserRememberedDevice
    {
        $cookieValue = $request->cookie(self::COOKIE_NAME);

        if (! is_string($cookieValue) || $cookieValue === '') {
            return null;
        }

        return UserRememberedDevice::query()
            ->where('device_token_hash', hash('sha256', $cookieValue))
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    /**
     * Is this device remembered for this particular user?
     */
    public function isDeviceRemembered(User $user, Request $request): bool
    {
        $device = $this->resolveDevice($request);

        if (! $device || (int) $device->user_id !== (int) $user->getKey()) {
            return false;
        }

        $this->touch($device, $request);

        return true;
    }

    /**
     * Record the use, and under a sliding lifetime push the expiry out.
     *
     * Queues the refreshed cookie itself rather than handing it back. Extending the
     * row without extending the cookie would quietly expire the device in the browser
     * while the database still believed in it, and every caller would have to remember
     * to queue it — this way none of them can forget.
     */
    public function touch(UserRememberedDevice $device, Request $request): void
    {
        $attributes = ['last_used_at' => Carbon::now()];

        if (! $this->isSliding()) {
            $device->update($attributes);

            return;
        }

        $attributes['expires_at'] = Carbon::now()->addDays($this->lifetimeDays());
        $device->update($attributes);

        // Reuse the token the browser already holds; reissuing on every request would
        // race between tabs for no benefit.
        $raw = $request->cookie(self::COOKIE_NAME);

        if (is_string($raw) && $raw !== '') {
            Cookie::queue($this->cookieFor($raw));
        }
    }

    /**
     * Remember this device for this user, returning the cookie to send back.
     */
    public function rememberDevice(User $user, Request $request): SymfonyCookie
    {
        $rawToken = Str::random(64);

        UserRememberedDevice::create([
            'user_id'           => $user->getKey(),
            'device_token_hash' => hash('sha256', $rawToken),
            'user_agent'        => $request->userAgent(),
            'ip_address'        => $request->ip(),
            'last_used_at'      => Carbon::now(),
            'expires_at'        => Carbon::now()->addDays($this->lifetimeDays()),
        ]);

        $this->prune($user);

        return $this->cookieFor($rawToken);
    }

    /**
     * Drop this device's row and clear the cookie.
     *
     * Called on logout: now that the cookie can restore a session, leaving it behind
     * would mean "log out" did not log the browser out.
     */
    public function forgetDevice(?User $user, Request $request): SymfonyCookie
    {
        $cookieValue = $request->cookie(self::COOKIE_NAME);

        if (is_string($cookieValue) && $cookieValue !== '') {
            $query = UserRememberedDevice::query()
                ->where('device_token_hash', hash('sha256', $cookieValue));

            // Scope to the user when we have one, so a stale cookie can still be
            // cleaned up on the way out when we don't.
            if ($user) {
                $query->where('user_id', $user->getKey());
            }

            $query->delete();
        }

        return Cookie::forget(self::COOKIE_NAME, '/');
    }

    /**
     * Forget every device for this user — "sign out everywhere".
     */
    public function forgetAllDevices(User $user): int
    {
        return UserRememberedDevice::query()->where('user_id', $user->getKey())->delete();
    }

    /**
     * Expired rows are dead weight and a small liability; clear them whenever we are
     * already writing for this user, and hold the list to maxPerUser().
     */
    private function prune(User $user): void
    {
        UserRememberedDevice::query()
            ->where('user_id', $user->getKey())
            ->where('expires_at', '<=', Carbon::now())
            ->delete();

        $keepIds = UserRememberedDevice::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_used_at')
            ->limit($this->maxPerUser())
            ->pluck('id');

        UserRememberedDevice::query()
            ->where('user_id', $user->getKey())
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    private function cookieFor(string $rawToken): SymfonyCookie
    {
        return Cookie::make(
            name: self::COOKIE_NAME,
            value: $rawToken,
            minutes: $this->lifetimeDays() * 24 * 60,
            path: '/',
            domain: null,
            secure: config('session.secure'),
            httpOnly: true,
            raw: false,
            sameSite: 'Lax',
        );
    }
}
