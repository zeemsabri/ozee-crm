<?php

namespace App\Services;

use App\Mail\OtpVerificationMail;
use App\Models\OtpVerification;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OtpService
{
    /**
     * Generate and send a 6-digit OTP to the given email for the project share token.
     */
    public function generate(string $email, string $projectToken): string
    {
        // Invalidate any previous unverified OTPs for this email + project
        OtpVerification::where('email', $email)
            ->where('project_token', $projectToken)
            ->whereNull('verified_at')
            ->delete();

        $otp = (string) random_int(100000, 999999);

        OtpVerification::create([
            'email'         => $email,
            'otp_hash'      => Hash::make($otp),
            'project_token' => $projectToken,
            'expires_at'    => Carbon::now()->addMinutes(10),
        ]);

        // The row on its own is useless: the code is hashed, so nobody can read it back.
        // This send was missing entirely — both the classic page and the portal told
        // people "Verification code sent to your email" while only ever writing a row.
        //
        // Queued, matching how the rest of the app sends mail. QUEUE_CONNECTION is
        // `database`, so this needs a queue worker running or the code is written and
        // never delivered.
        $projectName = Project::query()
            ->where('public_share_token', $projectToken)
            ->value('name');

        // sendNow, not queue/send: the mailable is ShouldQueue, so plain send() would
        // still defer it, and the queue here is database-backed and driven by cron —
        // up to a minute before anyone touches it. Someone is sitting on the
        // enter-your-code screen, so this one goes out during the request.
        Mail::to($email)->sendNow(new OtpVerificationMail(
            $otp,
            $projectName ?? config('branding.company.name', config('app.name'))
        ));

        return $otp;
    }

    /**
     * Verify the OTP. Returns the session token on success, or null on failure.
     *
     * @return array{session_token: string, guest_user_id: int}|null
     */
    public function verify(string $email, string $otp, string $projectToken): ?array
    {
        $record = OtpVerification::where('email', $email)
            ->where('project_token', $projectToken)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $record || $record->isExpired() || ! Hash::check($otp, $record->otp_hash)) {
            return null;
        }

        // Find or create a guest user for this email
        $guestUser = \App\Models\User::withTrashed()->where('email', $email)->first();

        if (! $guestUser) {
            $guestUser = \App\Models\User::create([
                'name'      => '',
                'email'     => $email,
                'password'  => Hash::make(Str::random(32)),
                'user_type' => 'guest',
            ]);
        }

        $sessionToken = Str::random(64);

        $record->update([
            'verified_at'   => Carbon::now(),
            'session_token' => $sessionToken,
            'guest_user_id' => $guestUser->id,
        ]);

        return [
            'session_token' => $sessionToken,
            'guest_user_id' => $guestUser->id,
            'needs_profile' => empty($guestUser->name) || empty($guestUser->metadata['phone'] ?? null),
        ];
    }

    /**
     * Resolve a guest user from a session token and project token.
     */
    public function resolveGuest(string $sessionToken, string $projectToken): ?\App\Models\User
    {
        $record = OtpVerification::where('session_token', $sessionToken)
            ->where('project_token', $projectToken)
            ->whereNotNull('verified_at')
            ->first();

        if (! $record || ! $record->guest_user_id) {
            return null;
        }

        return \App\Models\User::withTrashed()->find($record->guest_user_id);
    }
}
