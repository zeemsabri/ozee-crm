<?php

namespace App\Support;

/**
 * Reading and writing `emails.template_data`, which is stored in an awkward shape.
 *
 * `Email::$casts` declares the column as `array`, but every writer in the codebase assigns
 * `json_encode($data)` to it — so Eloquent JSON-encodes an already-encoded string and the
 * column ends up **double-encoded**. The accessor therefore hands back a *string*, which
 * is why the readers all do `json_decode($email->template_data, true)` and why that looks
 * wrong but works.
 *
 * The tempting fix is to store a plain array and let the cast do its job. Do not — at
 * least not in one step. Five separate readers still call `json_decode()` on the value
 * directly, and `json_decode(array)` is a **TypeError** in PHP 8, which the surrounding
 * `catch (\Exception)` blocks do not catch. A single row written in the "correct" shape
 * 500s the classic pending-approvals list for every user, not just for that email.
 *
 * So: `encode()` deliberately reproduces the existing double-encoded shape so new writers
 * stay compatible with old readers, and `decode()` accepts either shape so old rows and
 * any future normalised ones both work. When every reader goes through `decode()`, the
 * storage format can be changed in one commit by editing `encode()` alone.
 */
final class TemplateData
{
    /**
     * Read the value however it happens to be stored.
     *
     * @param  mixed  $value  the raw attribute off the model
     * @return array<string,mixed>
     */
    public static function decode($value): array
    {
        // Already an array: either a single-encoded row, or a value set in memory that has
        // not been through the DB yet.
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        // The double-encoded shape decodes once into a string that is itself JSON.
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Produce the value to assign to `$email->template_data`.
     *
     * Returns a JSON string, which the `array` cast then encodes again. That second
     * encoding is the whole point — see the class docblock.
     *
     * @param  array<string,mixed>  $data
     */
    public static function encode(array $data): string
    {
        return json_encode($data) ?: '[]';
    }
}
