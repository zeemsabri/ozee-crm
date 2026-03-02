<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProductivity extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'stats_json',
        'tasks_json',
        'timeline_json',
        'ai_report_json',
        'accuracy_json',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'stats_json' => 'array',
        'tasks_json' => 'array',
        'timeline_json' => 'array',
        'ai_report_json' => 'array',
        'accuracy_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Robustly handle malformed or unquoted JSON from AI outputs.
     */
    public function getAiReportJsonAttribute($value)
    {
        if (empty($value)) return [];

        // If already an array, return it
        if (is_array($value)) return $value;

        if (!is_string($value)) return $value;

        // Try standard decode
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $text = trim($value);
        
        // Handle double-encoded string: "{\"key\": \"val\"}"
        if (str_starts_with($text, '"') && str_ends_with($text, '"')) {
            $inner = json_decode($text, true);
            if (is_array($inner)) return $inner;
            if (is_string($inner)) $text = $inner;
        }

        // Try decode again
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // HEURISTIC: Find known keys and extract values between them
        $keys = [
            'headline', 'attendance_summary', 'focus_rating', 'engagement_narrative', 
            'accuracy_tip', 'improvement_suggestions', 'task_deep_dives', 'status'
        ];
        
        $keyPositions = [];
        foreach ($keys as $key) {
            $pattern = '/"' . $key . '"\s*:/';
            if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                $keyPositions[] = [
                    'key' => $key,
                    'start' => $matches[0][1],
                    'end' => $matches[0][1] + strlen($matches[0][0])
                ];
            }
        }

        // Sort by start position
        usort($keyPositions, fn($a, $b) => $a['start'] <=> $b['start']);

        if (empty($keyPositions)) return ['raw' => $value];

        $result = [];
        $totalCount = count($keyPositions);
        for ($i = 0; $i < $totalCount; $i++) {
            $current = $keyPositions[$i];
            $next = ($i + 1 < $totalCount) ? $keyPositions[$i+1] : null;
            
            $valStart = $current['end'];
            $valEnd = $next ? $next['start'] : strrpos($text, '}');
            if ($valEnd === false) $valEnd = strlen($text);

            $val = trim(substr($text, $valStart, $valEnd - $valStart));
            
            // Clean up trailing commas
            $val = rtrim($val, ',');
            $val = trim($val);

            // If it's quoted, decode it
            if (str_starts_with($val, '"') && str_ends_with($val, '"')) {
                $decodedVal = json_decode($val, true);
                $result[$current['key']] = ($decodedVal !== null) ? $decodedVal : trim($val, '"');
            } 
            // If it's an array/object, try decode
            elseif (str_starts_with($val, '[') || str_starts_with($val, '{')) {
                $decodedVal = json_decode($val, true);
                $result[$current['key']] = ($decodedVal !== null) ? $decodedVal : $val;
            }
            else {
                // Unquoted string! This is the fix.
                $result[$current['key']] = $val;
            }
        }
        
        return !empty($result) ? $result : ['raw' => $value];
    }
}
