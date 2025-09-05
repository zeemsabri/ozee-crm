<?php

namespace App\Services;

use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Service to handle transformations of presentation data from the Gemini API.
 */
class PresentationService
{
    /**
     * A map to translate minified keys to their full, human-readable versions.
     * This includes keys for all possible content_data structures.
     * @var array
     */
    private const KEY_MAP = [
        't' => 'title',
        'tn' => 'template_name', // <-- ADDED THIS LINE
        'cb' => 'content_blocks',
        'bt' => 'block_type',
        'cd' => 'content_data',
        'txt' => 'text',
        'lvl' => 'level',
        'itms' => 'items',
        'ic' => 'icon',
        'dsc' => 'description',
        'sn' => 'step_number',
        'p' => 'price',
        'ps' => 'payment_schedule',
        'tl' => 'timeline',
        'ph' => 'phase',
        'dur' => 'duration',
        'u' => 'url',
        'a' => 'alt',
    ];

    /**
     * A map to translate numeric block type codes to their string representations.
     * @var array
     */
    private const BLOCK_TYPE_MAP = [
        1 => 'heading',
        2 => 'paragraph',
        3 => 'list_with_icons',
        4 => 'details_list',
        5 => 'slogan',
        6 => 'feature_card',
        7 => 'step_card',
        8 => 'feature_list',
        9 => 'pricing_table',
        10 => 'timeline_table',
        11 => 'image',
        12 => 'image_block',
    ];

    /**
     * Translates a minified JSON string or array from the API into a human-readable format.
     *
     * @param string|array $minifiedData The minified JSON string or a decoded array.
     * @return array The expanded, human-readable array.
     */
    public function translate(string|array $minifiedData): array
    {
        if (is_string($minifiedData)) {
            $minifiedData = json_decode($minifiedData, true);
        }

        if (!is_array($minifiedData)) {
            throw new InvalidArgumentException('Input data must be a valid JSON string or an array.');
        }

        return $this->expandArray($minifiedData);
    }

    /**
     * Recursively expands an array by replacing minified keys and values.
     *
     * @param array $array The array to expand.
     * @return array The expanded array.
     */
    private function expandArray(array $array): array
    {
        $expandedArray = [];

        foreach ($array as $key => $value) {
            // Get the full, human-readable key from our map
            $expandedKey = self::KEY_MAP[$key] ?? $key;

            if ($expandedKey === 'block_type' && isset(self::BLOCK_TYPE_MAP[$value])) {
                // If the key is 'block_type', translate its numeric value to the string version
                $expandedArray[$expandedKey] = self::BLOCK_TYPE_MAP[$value];
            } elseif (is_array($value)) {
                // If the value is an array, expand it recursively
                $expandedArray[$expandedKey] = $this->expandArray($value);
            } else {
                // Otherwise, just assign the value
                $expandedArray[$expandedKey] = $value;
            }
        }

        return $expandedArray;
    }
}

