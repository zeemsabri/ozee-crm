<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'trigger_event',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::deleting(function (Workflow $workflow) {
            // When soft-deleting a workflow, also soft-delete its steps to keep data consistent
            if (method_exists($workflow, 'steps')) {
                $workflow->steps()->get()->each(function (WorkflowStep $step) {
                    $step->delete();
                });
            }
        });
    }

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
