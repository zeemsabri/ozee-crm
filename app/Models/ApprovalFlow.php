<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'approvable_type',
        'project_id',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function steps()
    {
        return $this->hasMany(ApprovalFlowStep::class)->orderBy('step_order');
    }

    public function instances()
    {
        return $this->hasMany(ApprovalInstance::class);
    }
}
