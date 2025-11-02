<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ValueSetValidator
{
    public function __construct(
        protected ValueDictionaryRegistry $registry
    ) {}

    /**
     * Validate a value against the allowed set for a given model field.
     *
     * If no value-set is known for the field, this is a no-op.
     * If a value-set exists and the value is not allowed:
     *  - When enforce flag is ON, throws ValidationException
     *  - When enforce flag is OFF, logs a warning and returns
     *
     * @param  string  $model  Model short name, e.g., "Task"
     * @param  string  $field  Field/column name, e.g., "status"
     * @param  mixed  $value  Scalar or array value(s)
     *
     * @throws ValidationException
     */
    public function validate(string $model, string $field, mixed $value): void
    {
        $def = $this->registry->for($model, $field);
        if (! $def) {
            // No known value set; skip validation.
            return;
        }

        $allowed = collect($def['values'] ?? [])->pluck('value')->map(fn ($v) => (string) $v)->all();
        $nullable = (bool) ($def['nullable'] ?? false);
        $multi = (bool) ($def['multi'] ?? false);

        // Allow null/empty if nullable
        if ($nullable && ($value === null || $value === '')) {
            return;
        }

        $invalid = [];

        if ($multi || is_array($value)) {
            $vals = Arr::wrap($value);
            foreach ($vals as $v) {
                // Treat enums as strings
                $vv = is_object($v) && isset($v->value) ? (string) $v->value : (string) $v;
                if (! in_array($vv, $allowed, true)) {
                    $invalid[] = $vv;
                }
            }
        } else {
            $vv = is_object($value) && isset($value->value) ? (string) $value->value : (string) $value;
            if (! in_array($vv, $allowed, true)) {
                $invalid[] = $vv;
            }
        }

        if (! empty($invalid)) {
            $this->handleInvalid($model, $field, $invalid, $allowed);
        }
    }

    protected function handleInvalid(string $model, string $field, array $invalid, array $allowed): void
    {
        $enforce = (bool) Config::get('value_sets.enforce_validation', Config::get('values.enforce_validation', false));
        $message = sprintf(
            'Invalid value(s) for %s.%s: [%s]. Allowed: [%s]'.
            ' (Toggle value_sets.enforce_validation or values.enforce_validation to enforce)',
            $model,
            $field,
            implode(', ', array_map(fn ($v) => (string) $v, $invalid)),
            implode(', ', $allowed)
        );

        if ($enforce) {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }

        $channel = Config::get('value_sets.log_channel', Config::get('values.log_channel', 'stack'));
        Log::channel($channel)->warning($message);
    }
}
