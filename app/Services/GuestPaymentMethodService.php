<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Saved payout methods for guests (contractors/suppliers reaching the app through a
 * public project share link).
 *
 * Storage: `users.metadata['payment_methods']`, per the decision not to add a table.
 * The value is an ENCRYPTED string rather than a plain nested array, because
 * `users.metadata` is an ordinary JSON column with no encryption cast, and these
 * records hold account numbers and IBANs. `bill_payment_details.details` — where the
 * same data ends up once a bill is raised — is `encrypted:array`, so storing it in the
 * clear here would be a step down from the protection it gets everywhere else.
 *
 * Raw account fields are never returned to the browser after they are saved. The guest
 * sees a masked one-line summary; the server reads the real values only when it copies
 * them onto a bill.
 */
class GuestPaymentMethodService
{
    /** The key inside users.metadata. */
    public const METADATA_KEY = 'payment_methods';

    /**
     * Method types, matching the `payment_method` literals BillController's `required_if`
     * rules key off — so a saved method drops straight onto a bill without translation.
     */
    public const TYPES = ['bank_local', 'bank_wire', 'paypal', 'payoneer', 'wise', 'crypto'];

    /**
     * Field keys we accept per type. Bank transfers vary by country (see
     * resources/js/ReactComponents/portal/paymentMethods.js, which drives the same shape
     * in the UI) so the bank types accept the union and the UI asks for the right subset.
     */
    private const ALLOWED_FIELDS = [
        'bank_local' => ['account_name', 'bank_name', 'account_number', 'bsb', 'iban', 'swift_code', 'branch_code', 'ifsc', 'sort_code', 'routing_number'],
        'bank_wire' => ['account_name', 'bank_name', 'account_number', 'bsb', 'iban', 'swift_code', 'branch_code', 'ifsc', 'sort_code', 'routing_number'],
        'paypal' => ['paypal_email'],
        'payoneer' => ['payoneer_email'],
        'wise' => ['wise_email'],
        'crypto' => ['wallet_address', 'coin_type'],
    ];

    /**
     * Fields that must be present for the resulting bill to satisfy BillController's
     * `required_if` rules — checked here so a guest can't save a method that would be
     * rejected later, when they're mid-way through submitting a bill.
     */
    private const REQUIRED_FIELDS = [
        'bank_local' => ['account_name', 'account_number', 'bsb'],
        'bank_wire' => ['account_name', 'swift_code'],
        'paypal' => ['paypal_email'],
        'payoneer' => ['payoneer_email'],
        'wise' => ['wise_email'],
        'crypto' => ['wallet_address', 'coin_type'],
    ];

    /**
     * Types that need at least one of a set, rather than all of it. An international
     * transfer is identified by an account number in some countries and an IBAN in
     * others, so demanding both would make the form unfillable in half of them.
     *
     * @var array<string, array<int, string>>
     */
    private const EITHER_FIELDS = [
        'bank_wire' => ['account_number', 'iban'],
    ];

    /**
     * Identifiers that have no column of their own in `bill_payment_details.details`.
     * They're folded into the notes line as well, so the accounts team actually sees
     * them on the admin bill screen (which renders a fixed set of keys).
     */
    private const EXTRA_IDENTIFIER_LABELS = [
        'branch_code' => 'Branch code',
        'ifsc' => 'IFSC',
        'sort_code' => 'Sort code',
        'routing_number' => 'Routing number',
    ];

    /**
     * All of a guest's saved methods, with raw field values intact.
     * Server-side use only — see maskedList() for anything leaving the app.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(User $user): array
    {
        $raw = $user->metadata[self::METADATA_KEY] ?? null;

        if (empty($raw) || ! is_string($raw)) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($raw), true);
        } catch (\Throwable $e) {
            // A rotated APP_KEY, or hand-edited metadata. Reading degrades to "none
            // saved" so the page still renders — but see readFailed(): we refuse to
            // write in that state, or the next save would destroy recoverable data.
            return [];
        }

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /**
     * True when a value is stored but could not be decrypted. Writing then would replace
     * an unreadable-but-present blob with whatever we happen to hold in memory.
     */
    private function readFailed(User $user): bool
    {
        $raw = $user->metadata[self::METADATA_KEY] ?? null;

        if (empty($raw) || ! is_string($raw)) {
            return false;
        }

        try {
            Crypt::decryptString($raw);

            return false;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * The browser-safe view: no account numbers, just enough to recognise the method.
     *
     * @return array<int, array<string, mixed>>
     */
    public function maskedList(User $user): array
    {
        return array_map(fn (array $m) => $this->mask($m), $this->all($user));
    }

    public function find(User $user, string $id): ?array
    {
        foreach ($this->all($user) as $method) {
            if (($method['id'] ?? null) === $id) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes  type, label, country, currency, fields
     * @return array{0: array<int, array<string, mixed>>, 1: string} masked list + new id
     *
     * @throws \InvalidArgumentException when a field the method type needs is blank
     */
    public function add(User $user, array $attributes): array
    {
        $type = $attributes['type'];
        $fields = $this->sanitizeFields($type, $attributes['fields'] ?? []);

        $missing = array_values(array_filter(
            self::REQUIRED_FIELDS[$type] ?? [],
            fn (string $key) => trim((string) ($fields[$key] ?? '')) === '',
        ));

        if ($missing) {
            throw new \InvalidArgumentException(
                'Missing required details for this method type: '.implode(', ', $missing).'.'
            );
        }

        $eitherOf = self::EITHER_FIELDS[$type] ?? [];
        if ($eitherOf) {
            $anyPresent = array_filter(
                $eitherOf,
                fn (string $key) => trim((string) ($fields[$key] ?? '')) !== '',
            );

            if (! $anyPresent) {
                throw new \InvalidArgumentException(
                    'Give either an account number or an IBAN for an international transfer.'
                );
            }
        }

        $methods = $this->all($user);

        $method = [
            'id' => 'pm_'.Str::random(12),
            'type' => $type,
            'label' => trim((string) $attributes['label']),
            'country' => $attributes['country'] ?? null,
            'currency' => $attributes['currency'] ?? null,
            // First method saved becomes the default, so there is always one to preselect.
            'is_default' => count($methods) === 0,
            'fields' => $fields,
            'created_at' => now()->toIso8601String(),
        ];

        $methods[] = $method;
        $this->persist($user, $methods);

        return [$this->maskedList($user), $method['id']];
    }

    /**
     * @return array<int, array<string, mixed>> the masked list after removal
     */
    public function remove(User $user, string $id): array
    {
        $methods = array_values(array_filter($this->all($user), fn ($m) => ($m['id'] ?? null) !== $id));

        // Never leave the guest with methods but no default.
        if ($methods && ! array_filter($methods, fn ($m) => ! empty($m['is_default']))) {
            $methods[0]['is_default'] = true;
        }

        $this->persist($user, $methods);

        return $this->maskedList($user);
    }

    /**
     * @return array<int, array<string, mixed>> the masked list after the change
     */
    public function makeDefault(User $user, string $id): array
    {
        $methods = array_map(function (array $m) use ($id) {
            $m['is_default'] = ($m['id'] ?? null) === $id;

            return $m;
        }, $this->all($user));

        $this->persist($user, $methods);

        return $this->maskedList($user);
    }

    /**
     * Translate a saved method into the `payment_method` + `details` pair that
     * `bill_payment_details` expects. Keys match what BillController::store writes, so
     * the admin bill screens render a guest-submitted bill identically to an internal one.
     *
     * @param  array<string, mixed>  $method
     * @return array{payment_method: string, details: array<string, mixed>}
     */
    public function toBillPaymentDetail(array $method): array
    {
        $fields = $method['fields'] ?? [];

        // The admin bill screens render a fixed key set. Anything outside it (IFSC, sort
        // code, …) would be invisible there, so it's also written into the notes line.
        $extras = [];
        foreach (self::EXTRA_IDENTIFIER_LABELS as $key => $label) {
            if (! empty($fields[$key])) {
                $extras[] = $label.': '.$fields[$key];
            }
        }

        $context = array_filter([
            $method['label'] ?? null,
            $method['country'] ?? null,
            ! empty($method['currency']) ? 'Account currency '.$method['currency'] : null,
        ]);

        $notes = implode(' · ', array_merge(['Submitted by the supplier'], $context, $extras));

        return [
            'payment_method' => $method['type'],
            'details' => [
                'account_name' => $fields['account_name'] ?? null,
                // BillController's `required_if` rules want an account number for bank
                // transfers, and its update() would reject an edit without one. IBAN-only
                // countries fill it from the IBAN so an admin can still edit the bill.
                'account_number' => $fields['account_number'] ?? $fields['iban'] ?? null,
                'bank_name' => $fields['bank_name'] ?? null,
                'bsb' => $fields['bsb'] ?? null,
                'swift_code' => $fields['swift_code'] ?? null,
                'iban' => $fields['iban'] ?? null,
                // BillController validates these but doesn't persist them — a known bug
                // on the admin path. Guest-submitted bills keep them so the destination
                // isn't lost for non-bank methods.
                'paypal_email' => $fields['paypal_email'] ?? null,
                'payoneer_email' => $fields['payoneer_email'] ?? null,
                'wise_email' => $fields['wise_email'] ?? null,
                'wallet_address' => $fields['wallet_address'] ?? null,
                'coin_type' => $fields['coin_type'] ?? null,
                'branch_code' => $fields['branch_code'] ?? null,
                'ifsc' => $fields['ifsc'] ?? null,
                'sort_code' => $fields['sort_code'] ?? null,
                'routing_number' => $fields['routing_number'] ?? null,
                'notes' => $notes,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $methods
     */
    private function persist(User $user, array $methods): void
    {
        if ($this->readFailed($user)) {
            throw new \RuntimeException(
                'Your saved payment methods could not be read, so nothing was changed. Contact the team.'
            );
        }

        $metadata = $user->metadata ?? [];
        $metadata[self::METADATA_KEY] = $methods
            ? Crypt::encryptString(json_encode(array_values($methods)))
            : null;

        if ($metadata[self::METADATA_KEY] === null) {
            unset($metadata[self::METADATA_KEY]);
        }

        $user->update(['metadata' => $metadata]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    private function sanitizeFields(string $type, array $fields): array
    {
        $allowed = self::ALLOWED_FIELDS[$type] ?? [];
        $clean = [];

        foreach ($allowed as $key) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value !== '') {
                $clean[$key] = mb_substr($value, 0, 255);
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $method
     * @return array<string, mixed>
     */
    private function mask(array $method): array
    {
        $fields = $method['fields'] ?? [];
        $type = $method['type'] ?? 'bank_wire';

        $parts = array_filter([
            $this->typeLabel($type),
            $method['country'] ?? null,
            $method['currency'] ?? null,
        ]);

        if (in_array($type, ['bank_local', 'bank_wire'], true)) {
            $parts[] = $fields['bank_name'] ?? null;
            $parts[] = ! empty($fields['bsb']) ? 'BSB '.$fields['bsb'] : null;
            $identifier = $fields['account_number'] ?? $fields['iban'] ?? null;
            $parts[] = $identifier ? $this->maskTail($identifier) : null;
        } else {
            $email = $fields['paypal_email'] ?? $fields['payoneer_email'] ?? $fields['wise_email'] ?? null;
            $parts[] = $email ? $this->maskEmail($email) : null;
            $parts[] = ! empty($fields['coin_type']) ? $fields['coin_type'] : null;
            $parts[] = ! empty($fields['wallet_address']) ? $this->maskTail($fields['wallet_address']) : null;
        }

        return [
            'id' => $method['id'] ?? null,
            'type' => $type,
            'type_label' => $this->typeLabel($type),
            'label' => $method['label'] ?? '',
            'country' => $method['country'] ?? null,
            'currency' => $method['currency'] ?? null,
            'is_default' => (bool) ($method['is_default'] ?? false),
            'detail_line' => implode(' · ', array_filter($parts)),
        ];
    }

    private function typeLabel(string $type): string
    {
        return [
            'bank_local' => 'Bank transfer (local)',
            'bank_wire' => 'Bank transfer (international)',
            'paypal' => 'PayPal',
            'payoneer' => 'Payoneer',
            'wise' => 'Wise',
            'crypto' => 'Crypto',
        ][$type] ?? $type;
    }

    private function maskTail(string $value): string
    {
        $trimmed = preg_replace('/\s+/', '', $value);

        return mb_strlen($trimmed) <= 4 ? '••••' : '••••'.mb_substr($trimmed, -4);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return $this->maskTail($email);
        }

        $visible = mb_substr($name, 0, 2);

        return $visible.str_repeat('•', max(1, mb_strlen($name) - 2)).'@'.$domain;
    }
}
