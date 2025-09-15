<?php

namespace App\Casts;

use App\Enums\MilestoneStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use ValueError;

/**
 * Custom cast to map legacy string values (different case/format) to MilestoneStatus enum.
 *
 * Backward compatibility examples handled:
 * - "In Progress", "IN_PROGRESS", "in-progress" => MilestoneStatus::InProgress
 * - General case-insensitive, underscore/hyphen/space normalized comparisons
 */
class MilestoneStatusCast implements CastsAttributes
{
    public function get(Model $model, string $key, $value, array $attributes): ?MilestoneStatus
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof MilestoneStatus) {
            return $value;
        }

        if (is_string($value)) {
            // Try exact match first
            if ($enum = MilestoneStatus::tryFrom($value)) {
                return $enum;
            }

            // Normalize and compare against normalized enum values (case-insensitive and format-insensitive)
            $normalized = self::normalize($value);
            foreach (MilestoneStatus::cases() as $case) {
                if (self::normalize($case->value) === $normalized) {
                    return $case;
                }
            }
        }

        // Preserve Laravel/Enum behavior for truly invalid values
        throw new ValueError('"' . (is_scalar($value) ? (string)$value : gettype($value)) . '" is not a valid backing value for enum ' . MilestoneStatus::class);
    }

    public function set(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof MilestoneStatus) {
            return $value->value;
        }

        if (is_string($value)) {
            // Accept exact enum value
            if ($enum = MilestoneStatus::tryFrom($value)) {
                return $enum->value;
            }

            // Normalize legacy strings and map to canonical enum value
            $normalized = self::normalize($value);
            foreach (MilestoneStatus::cases() as $case) {
                if (self::normalize($case->value) === $normalized) {
                    return $case->value;
                }
            }
        }

        throw new ValueError('Invalid value for ' . MilestoneStatus::class . ' given to attribute ' . $key);
    }

    private static function normalize(string $value): string
    {
        $v = strtolower(trim($value));
        $v = str_replace(['_', '-'], ' ', $v);
        // Collapse multiple spaces
        $v = preg_replace('/\s+/', ' ', $v);

        // Map known legacy synonyms to canonical forms
        $synonyms = [
            'not started' => 'pending',
            'cancelled' => 'canceled',
        ];
        if (array_key_exists($v, $synonyms)) {
            $v = $synonyms[$v];
        }

        return $v;
    }
}
