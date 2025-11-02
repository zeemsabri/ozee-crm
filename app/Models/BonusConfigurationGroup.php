<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusConfigurationGroup extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'user_id',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = ['bonusConfigurations'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['configurations'];

    /**
     * Get the configurations for the group.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    public function getConfigurationsAttribute()
    {
        return $this->bonusConfigurations;
    }

    /**
     * Get the user that owns the bonus configuration group.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bonus configurations that belong to the group.
     */
    public function bonusConfigurations()
    {
        return $this->belongsToMany(BonusConfiguration::class, 'bonus_configuration_group_items', 'group_id', 'configuration_id')
            ->withPivot('sort_order')
            ->orderBy('bonus_configuration_group_items.sort_order')
            ->withTimestamps();
    }

    /**
     * Get the projects that use this bonus configuration group.
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_bonus_configuration_group', 'group_id', 'project_id')
            ->withTimestamps();
    }

    /**
     * Duplicate this group with all its configurations.
     *
     * @param  string  $newName  The name for the duplicated group
     * @return BonusConfigurationGroup
     */
    public function duplicate($newName = null)
    {
        // Create a new group with the same attributes
        $newGroup = $this->replicate(['id']);

        // Set a new name if provided, otherwise append "(Copy)" to the original name
        $newGroup->name = $newName ?: $this->name.' (Copy)';
        $newGroup->save();

        // Copy all configurations to the new group
        foreach ($this->bonusConfigurations as $configuration) {
            $newGroup->bonusConfigurations()->attach($configuration->id, [
                'sort_order' => $configuration->pivot->sort_order,
            ]);
        }

        return $newGroup;
    }
}
