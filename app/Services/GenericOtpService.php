<?php

namespace App\Services;

use App\Models\UserOtp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class GenericOtpService
{
    /**
     * Generate a new OTP and send it via email.
     */
    public function generate(string $identifier, string $context = 'login', int $expiresInMinutes = 10, int $maxAttempts = 5, ?array $meta = []): string
    {
        // Enforce 60-second cooldown per identifier + context
        $latest = UserOtp::where('identifier', $identifier)
            ->where('context', $context)
            ->latest('created_at')
            ->first();

        if ($latest && $latest->created_at->diffInSeconds(now()) < 60) {
            throw ValidationException::withMessages([
                'otp' => 'Please wait a moment before requesting another code.',
            ]);
        }

        // Invalidate old unverified OTPs
        UserOtp::where('identifier', $identifier)
            ->where('context', $context)
            ->whereNull('verified_at')
            ->delete();

        $otp = (string) random_int(100000, 999999);

        UserOtp::create([
            'identifier' => $identifier,
            'context' => $context,
            'otp_hash' => Hash::make($otp),
            'expires_at' => Carbon::now()->addMinutes($expiresInMinutes),
            'max_attempts' => $maxAttempts,
            'meta' => $meta,
        ]);

        return $otp;
    }

    /**
     * Verify a given OTP.
     */
    public function verify(string $identifier, string $otp, string $context = 'login'): array
    {
        $record = UserOtp::where('identifier', $identifier)
            ->where('context', $context)
            ->whereNull('verified_at')
            ->latest('created_at')
            ->first();

        if (!$record) {
            return ['success' => false, 'message' => 'No active OTP found. Please request a new one.', 'otp_record' => null];
        }

        if ($record->isExpired()) {
            return ['success' => false, 'message' => 'OTP has expired. Please request a new one.', 'otp_record' => $record];
        }

        if ($record->hasExceededMaxAttempts()) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please request a new code.', 'otp_record' => $record];
        }

        $record->increment('attempts');

        if (!Hash::check($otp, $record->otp_hash)) {
            if ($record->hasExceededMaxAttempts()) {
                return ['success' => false, 'message' => 'Too many failed attempts. Please request a new code.', 'otp_record' => $record];
            }
            return ['success' => false, 'message' => 'Invalid OTP code.', 'otp_record' => $record];
        }

        // Success
        $record->update(['verified_at' => Carbon::now()]);

        return ['success' => true, 'message' => 'OTP verified successfully.', 'otp_record' => $record];
    }
}
