<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;

/**
 * The portal user's own details.
 *
 * Name lives on the `users` row; everything else goes in `users.metadata`, per the
 * decision not to add a table for portal-specific fields.
 *
 * The verification block — date of birth, ID document, residential address — is what
 * banks ask for before an international transfer clears, so it is held ENCRYPTED under
 * a single metadata key rather than as plain JSON. `users.metadata` has no encryption
 * cast, and a government ID number sitting in a readable column is the kind of thing
 * you only get to be wrong about once. Same treatment as the payout methods in
 * GuestPaymentMethodService.
 *
 * Unlike payout methods, these values ARE returned to their owner in full: the profile
 * form edits them in place, so a masked round-trip would make them uneditable.
 */
class PortalProfileService
{
    public const METADATA_KEY = 'verification_details';

    /** Everything the verification block can hold. */
    public const FIELDS = ['dob', 'id_type', 'id_number', 'city', 'address', 'postcode'];

    /**
     * What a bank actually needs. `postcode` is genuinely optional in several of the
     * countries suppliers bill from, and `id_type` always has a default.
     */
    public const REQUIRED_FIELDS = ['dob', 'id_number', 'city', 'address'];

    public const ID_TYPES = [
        'national_id' => 'National ID card (CNIC)',
        'aadhaar_pan' => 'Aadhaar / PAN',
        'passport' => 'Passport',
        'drivers_licence' => 'Driver licence',
    ];

    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $verification = $this->verification($user);
        $missing = $this->missingVerificationFields($verification);

        return [
            'name'          => $user->name ?? '',
            'email'         => $user->email,
            'phone'         => $user->metadata['phone'] ?? '',
            'business_name' => $user->metadata['business_name'] ?? '',
            'verification'  => $verification,
            // Drives the "Complete / N fields missing" pill in the design.
            'verification_missing'  => $missing,
            'verification_complete' => $missing === [],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function verification(User $user): array
    {
        $blank = array_fill_keys(self::FIELDS, '');
        $raw = $user->metadata[self::METADATA_KEY] ?? null;

        if (empty($raw) || ! is_string($raw)) {
            return $blank;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($raw), true);
        } catch (\Throwable $e) {
            // Rotated APP_KEY or hand-edited metadata: show the form empty rather than
            // failing the page. See writeGuard() — we refuse to overwrite in this state.
            return $blank;
        }

        if (! is_array($decoded)) {
            return $blank;
        }

        return array_merge($blank, array_intersect_key($decoded, $blank));
    }

    /**
     * @param  array<string, mixed>  $attributes  name, phone, business_name, verification
     */
    public function update(User $user, array $attributes): void
    {
        $metadata = $user->metadata ?? [];

        if (array_key_exists('phone', $attributes)) {
            $metadata['phone'] = trim((string) $attributes['phone']);
        }

        if (array_key_exists('business_name', $attributes)) {
            $business = trim((string) $attributes['business_name']);
            $metadata['business_name'] = $business !== '' ? $business : null;
        }

        if (array_key_exists('verification', $attributes) && is_array($attributes['verification'])) {
            $this->writeGuard($user);

            $clean = [];
            foreach (self::FIELDS as $field) {
                $value = trim((string) ($attributes['verification'][$field] ?? ''));
                if ($value !== '') {
                    $clean[$field] = mb_substr($value, 0, 255);
                }
            }

            if ($clean) {
                $metadata[self::METADATA_KEY] = Crypt::encryptString(json_encode($clean));
            } else {
                unset($metadata[self::METADATA_KEY]);
            }
        }

        $update = ['metadata' => $metadata];

        if (array_key_exists('name', $attributes)) {
            $update['name'] = trim((string) $attributes['name']);
        }

        $user->update($update);
    }

    /**
     * @param  array<string, string>  $verification
     * @return array<int, string>
     */
    public function missingVerificationFields(array $verification): array
    {
        return array_values(array_filter(
            self::REQUIRED_FIELDS,
            fn (string $field) => trim((string) ($verification[$field] ?? '')) === '',
        ));
    }

    /**
     * Refuse to write when a value is stored but won't decrypt — overwriting would
     * replace an unreadable-but-present record with whatever we hold in memory.
     */
    private function writeGuard(User $user): void
    {
        $raw = $user->metadata[self::METADATA_KEY] ?? null;

        if (empty($raw) || ! is_string($raw)) {
            return;
        }

        try {
            Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Your saved verification details could not be read, so nothing was changed. Contact the team.'
            );
        }
    }
}
