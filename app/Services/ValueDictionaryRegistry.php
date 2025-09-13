<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class ValueDictionaryRegistry
{
    /**
     * Get the full value dictionary for all configured models/fields.
     */
    public function all(): array
    {
        $ttl = (int) Config::get('value_sets.cache_ttl', 300);
        return Cache::remember('value-dictionaries', $ttl, function () {
            return $this->buildAll();
        });
    }

    /**
     * Get the value set for a single model/field or null if unknown.
     */
    public function for(string $model, string $field): ?array
    {
        $all = $this->all();
        return $all[$model]['fields'][$field] ?? null;
    }

    protected function buildAll(): array
    {
        $result = [];
        $hints = Config::get('value_sets.models', []);

        // Models we should consider for auto-detection (from hints or filesystem)
        $candidateModels = array_unique(array_merge(
            array_keys($hints),
            $this->discoverModelNames()
        ));

        foreach ($candidateModels as $modelName) {
            $fields = [];

            // 1) Config-driven hints take precedence
            if (isset($hints[$modelName])) {
                foreach ($hints[$modelName] as $field => $def) {
                    $normalized = $this->resolveFromHint($modelName, $field, (array)$def);
                    if ($normalized) {
                        $fields[$field] = $normalized;
                    }
                }
            }

            // 2) Auto-detect PHP enums from model casts (if not already defined)
            $enumCasts = $this->detectEnumCasts($modelName);
            foreach ($enumCasts as $field => $enumClass) {
                if (!isset($fields[$field])) {
                    $fields[$field] = $this->fromPhpEnum($enumClass, [
                        'source' => 'php_enum',
                        'enum' => $enumClass,
                    ]);
                }
            }

            if (!empty($fields)) {
                $result[$modelName] = [
                    'fields' => $fields,
                ];
            }
        }

        return $result;
    }

    protected function resolveFromHint(string $modelName, string $field, array $def): ?array
    {
        $source = $def['source'] ?? null;
        if (!$source) return null;
        switch ($source) {
            case 'php_enum':
                $enumClass = $def['enum'] ?? null;
                if (!$enumClass || !enum_exists($enumClass)) return null;
                return $this->fromPhpEnum($enumClass, $def);
            case 'model_const':
                $constName = $def['const'] ?? null;
                $class = $this->modelClassFromName($modelName);
                if (!$class || !$constName) return null;
                if (!defined($class . '::' . $constName)) return null;
                $values = constant($class . '::' . $constName);
                return $this->normalizeValues($values, [
                    'source' => 'model_const:' . $class . '::' . $constName,
                ]);
            case 'config':
                $path = $def['path'] ?? null;
                if (!$path) return null;
                $values = Config::get($path);
                return $this->normalizeValues($values, [
                    'source' => 'config:' . $path,
                ]);
            case 'db':
                $table = $def['table'] ?? null;
                $valCol = $def['value_column'] ?? 'id';
                $labelCol = $def['label_column'] ?? $valCol;
                $activeCol = $def['active_column'] ?? null;
                if (!$table) return null;
                $query = DB::table($table)->select([$valCol . ' as value', $labelCol . ' as label']);
                if ($activeCol) $query->where($activeCol, true);
                $rows = $query->orderBy($labelCol)->get()->map(fn($r) => ['value' => (string)$r->value, 'label' => (string)$r->label])->toArray();
                return [
                    'type' => 'enum',
                    'values' => $rows,
                    'source' => 'db:' . $table,
                    'nullable' => (bool)($def['nullable'] ?? false),
                    'multi' => (bool)($def['multi'] ?? false),
                ];
            default:
                return null;
        }
    }

    protected function fromPhpEnum(string $enumClass, array $def = []): array
    {
        $cases = [];
        foreach ($enumClass::cases() as $case) {
            $value = method_exists($case, 'value') ? $case->value : $case->name;
            $label = Str::headline($case->name);
            $cases[] = ['value' => (string)$value, 'label' => $label];
        }
        return [
            'type' => 'enum',
            'values' => $cases,
            'source' => 'php_enum:' . $enumClass,
            'nullable' => (bool)($def['nullable'] ?? false),
            'multi' => (bool)($def['multi'] ?? false),
        ];
    }

    /**
     * Normalize arrays from constants/config to [ ['value'=>..,'label'=>..], ... ]
     */
    protected function normalizeValues($values, array $meta = []): ?array
    {
        if (is_null($values)) return null;
        $out = [];
        if (is_array($values)) {
            // Assoc map value=>label OR list
            $assoc = Arr::isAssoc($values);
            foreach ($values as $k => $v) {
                if ($assoc) {
                    $out[] = ['value' => (string)$k, 'label' => (string)$v];
                } else {
                    $out[] = ['value' => (string)$v, 'label' => Str::headline((string)$v)];
                }
            }
        } else {
            // Single scalar
            $out[] = ['value' => (string)$values, 'label' => Str::headline((string)$values)];
        }
        return array_merge([
            'type' => 'enum',
            'values' => $out,
            'nullable' => false,
            'multi' => false,
        ], $meta);
    }

    /**
     * Try to discover model names by scanning app/Models directory.
     */
    protected function discoverModelNames(): array
    {
        $dir = app_path('Models');
        if (!File::exists($dir)) return [];
        $names = [];
        foreach (File::files($dir) as $file) {
            $names[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }
        return $names;
    }

    /**
     * Detect PHP enum-backed casts on a model class.
     */
    protected function detectEnumCasts(string $modelName): array
    {
        $class = $this->modelClassFromName($modelName);
        if (!$class || !class_exists($class)) return [];
        $instance = new $class();
        if (!property_exists($instance, 'casts')) return [];
        $out = [];
        foreach ($instance->getCasts() as $field => $cast) {
            if (is_string($cast) && class_exists($cast) && enum_exists($cast)) {
                $out[$field] = $cast;
            }
        }
        return $out;
    }

    protected function modelClassFromName(string $modelName): ?string
    {
        $class = 'App\\Models\\' . ltrim($modelName, '\\');
        return class_exists($class) ? $class : null;
    }
}
