<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'trigger_event',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    public function logs()
    {
        return $this->hasMany(ExecutionLog::class, 'workflow_id');
    }

    /**
     * Polymorphic schedules attached to this Workflow.
     */
    public function schedules()
    {
        return $this->morphMany(\App\Models\Schedule::class, 'scheduledItem');
    }
}
