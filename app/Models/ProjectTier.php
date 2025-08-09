<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTier extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'point_multiplier',
        'min_profit_margin_percentage',
        'max_profit_margin_percentage',
        'min_client_amount_pkr',
        'max_client_amount_pkr',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'point_multiplier' => 'decimal:2',
        'min_profit_margin_percentage' => 'decimal:2',
        'max_profit_margin_percentage' => 'decimal:2',
        'min_client_amount_pkr' => 'decimal:2',
        'max_client_amount_pkr' => 'decimal:2',
    ];

    /**
     * Get the projects that belong to this tier.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function projects()
    {
        return $this->hasMany(Project::class, 'project_tier_id');
    }
}
