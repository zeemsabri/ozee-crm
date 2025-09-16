<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ValueError;

/**
 * Reusable normalizing enum cast.
 *
 * Usage:
 *  - In your model casts: 'status' => MilestoneStatusCast::class. // defaults to MilestoneStatus for BC
 *  - Or parameterized: 'status' => MilestoneStatusCast::class . ':' . \App\Enums\TaskStatus::class
 *
 * It tolerates legacy variants like different case, underscores/hyphens, camelCase, and known synonyms.
 */
class MilestoneStatusCast implements CastsAttributes
{
    /** @var class-string<\UnitEnum> */
    protected string $enumClass;

    /** @var array<string,string> Map of normalized legacy => normalized canonical */
    protected array $synonyms;

    public function __construct(string $enumClass = null, string $synonymsJson = null)
    {
        // Default to MilestoneStatus when not parameterized
        $this->enumClass = $enumClass && enum_exists($enumClass)
            ? $enumClass
            : (enum_exists('App\\Enums\\MilestoneStatus') ? 'App\\Enums\\MilestoneStatus' : $enumClass);

        if (!$this->enumClass || !enum_exists($this->enumClass)) {
            throw new InvalidArgumentException('MilestoneStatusCast requires a valid enum class.');
        }

        $this->synonyms = $this->defaultSynonymsFor($this->enumClass);

        if ($synonymsJson) {
            $extra = json_decode($synonymsJson, true);
            if (is_array($extra)) {
                // Normalize keys/values of provided overrides
                $normalized = [];
                foreach ($extra as $k => $v) {
                    $normalized[self::normalize((string)$k)] = self::normalize((string)$v);
                }
                $this->synonyms = array_merge($this->synonyms, $normalized);
            }
        }
    }

    public function get(Model $model, string $key, $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $enumClass = $this->enumClass;

        if ($value instanceof \UnitEnum) {
            return $value;
        }

        if (is_string($value)) {
            // Try exact backed-value match first
            if (method_exists($enumClass, 'tryFrom')) {
                $enum = $enumClass::tryFrom($value);
                if ($enum) {
                    return $enum;
                }
            }

            // Normalize input and attempt tolerant match against enum values and names
            $normalized = self::normalize($value);
            $target = $this->synonyms[$normalized] ?? $normalized;

            foreach ($enumClass::cases() as $case) {
                $valNorm = self::normalize(self::caseValue($case));
                $nameNorm = self::normalize($case->name);
                if ($valNorm === $target || $nameNorm === $target) {
                    return $case;
                }
            }
        }

        throw new ValueError('"' . (is_scalar($value) ? (string)$value : gettype($value)) . '" is not a valid backing value for enum ' . $enumClass);
    }

    public function set(Model $model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $enumClass = $this->enumClass;

        if ($value instanceof \UnitEnum) {
            return self::caseValue($value);
        }

        if (is_string($value)) {
            // Accept exact
            if (method_exists($enumClass, 'tryFrom')) {
                $enum = $enumClass::tryFrom($value);
                if ($enum) {
                    return self::caseValue($enum);
                }
            }

            $normalized = self::normalize($value);
            $target = $this->synonyms[$normalized] ?? $normalized;
            foreach ($enumClass::cases() as $case) {
                $valNorm = self::normalize(self::caseValue($case));
                $nameNorm = self::normalize($case->name);
                if ($valNorm === $target || $nameNorm === $target) {
                    return self::caseValue($case);
                }
            }
        }

        throw new ValueError('Invalid value for ' . $enumClass . ' given to attribute ' . $key);
    }

    protected static function caseValue(\UnitEnum $case): string
    {
        return property_exists($case, 'value') ? (string)$case->value : (string)$case->name;
    }

    protected static function splitCamel(string $value): string
    {
        // Insert spaces between camelCase or PascalCase boundaries
        return preg_replace('/(?<!^)[A-Z]/', ' $0', $value);
    }

    protected static function normalize(string $value): string
    {
        $v = self::splitCamel(trim($value));
        $v = strtolower($v);
        $v = str_replace(['_', '-'], ' ', $v);
        // Collapse multiple spaces
        $v = preg_replace('/\s+/', ' ', $v);
        return $v;
    }

    protected function defaultSynonymsFor(string $enumClass): array
    {
        // Map normalized legacy inputs to normalized canonical values
        return match ($enumClass) {
            'App\\Enums\\TaskStatus' => [
                'todo' => 'to do',
                'inprogress' => 'in progress',
            ],
            'App\\Enums\\SubtaskStatus' => [
                'todo' => 'to do',
                'inprogress' => 'in progress',
            ],
            'App\\Enums\\MilestoneStatus' => [
                'not started' => 'pending',
                'cancelled' => 'canceled',
                'inprogress' => 'in progress',
            ],
            default => [],
        };
    }
}
