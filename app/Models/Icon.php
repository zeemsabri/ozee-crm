<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Icon extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'svg_content',
    ];

    /**
     * Get the activity log options for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the components that use this icon.
     */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    /**
     * Validate SVG content.
     */
    public static function validateSvgContent(string $svgContent): bool
    {
        // Basic validation - check if it starts with SVG tag
        if (! preg_match('/<svg[^>]*>/', $svgContent)) {
            return false;
        }

        // Check for potentially malicious content
        $disallowedPatterns = [
            '/<script[^>]*>/',
            '/javascript:/',
            '/eval\(/',
            '/on[a-z]+\s*=/',
        ];

        foreach ($disallowedPatterns as $pattern) {
            if (preg_match($pattern, $svgContent)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize SVG content.
     */
    public static function sanitizeSvgContent(string $svgContent): string
    {
        // Remove any script tags
        $sanitized = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $svgContent);

        // Remove event handlers
        $sanitized = preg_replace('/\bon[a-z]+\s*=\s*(["\'])[^"\']*\1/i', '', $sanitized);

        // Remove javascript: URLs
        $sanitized = preg_replace('/javascript:[^"\']*/', '', $sanitized);

        // Remove eval() calls
        $sanitized = preg_replace('/eval\([^)]*\)/', '', $sanitized);

        return $sanitized;
    }
}
