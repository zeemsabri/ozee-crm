<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'target_audience',
        'services_offered',
        'goal',
        'ai_persona',
        'email_template',
        'is_active',
    ];

    protected $casts = [
        'services_offered' => 'array',
        'is_active' => 'boolean',
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function shareableResources()
    {
        return $this->belongsToMany(ShareableResource::class, 'campaign_shareable_resource')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
