<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRememberedDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class RememberDeviceService
{
    const COOKIE_NAME = 'remember_device';
    const EXPIRATION_DAYS = 7;

    /**
     * Check if the device is remembered and valid.
     */
    public function isDeviceRemembered(User $user, Request $request): bool
    {
        $cookieValue = $request->cookie(self::COOKIE_NAME);

        if (!$cookieValue) {
            return false;
        }

        // The cookie value will be the raw token
        $tokenHash = hash('sha256', $cookieValue);

        $device = UserRememberedDevice::where('user_id', $user->id)
            ->where('device_token_hash', $tokenHash)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($device) {
            // Update last used at
            $device->update(['last_used_at' => Carbon::now()]);
            return true;
        }

        return false;
    }

    /**
     * Remember the device for the user for 7 days.
     */
    public function rememberDevice(User $user, Request $request)
    {
        $rawToken = Str::random(64);
        $tokenHash = hash('sha256', $rawToken);

        UserRememberedDevice::create([
            'user_id' => $user->id,
            'device_token_hash' => $tokenHash,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'last_used_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays(self::EXPIRATION_DAYS),
        ]);

        return Cookie::make(
            self::COOKIE_NAME,
            $rawToken,
            self::EXPIRATION_DAYS * 24 * 60, // minutes
            '/',
            null,
            config('session.secure'),
            true, // HttpOnly
            false, // raw
            'Lax' // SameSite
        );
    }

    /**
     * Forget the remembered device (if requested).
     */
    public function forgetDevice(User $user, Request $request)
    {
        $cookieValue = $request->cookie(self::COOKIE_NAME);

        if ($cookieValue) {
            $tokenHash = hash('sha256', $cookieValue);
            UserRememberedDevice::where('user_id', $user->id)
                ->where('device_token_hash', $tokenHash)
                ->delete();
        }

        return Cookie::forget(self::COOKIE_NAME);
    }
}
