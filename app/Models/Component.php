<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Component extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'definition',
        'icon_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'definition' => 'array',
    ];

    /**
     * Get the activity log options for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the icon associated with the component.
     */
    public function icon(): BelongsTo
    {
        return $this->belongsTo(Icon::class);
    }

    /**
     * Validate the component definition.
     *
     * @param array $definition
     * @return bool
     */
    public static function validateDefinition(array $definition): bool
    {
        // Basic validation - ensure the definition has the required structure
        if (!isset($definition['default']) || !is_array($definition['default'])) {
            return false;
        }

        // Check for required default properties
        if (!isset($definition['default']['size']) ||
            !isset($definition['default']['size']['width']) ||
            !isset($definition['default']['size']['height'])) {
            return false;
        }

        // Additional validation can be added as needed
        return true;
    }
}
