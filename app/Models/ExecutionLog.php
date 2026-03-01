<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExecutionLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'workflow_id',
        'execution_id',
        'step_id',
        'triggering_object_id',
        'parent_execution_log_id',
        'status',
        'input_context',
        'raw_output',
        'parsed_output',
        'error_message',
        'duration_ms',
        'token_usage',
        'cost',
        'executed_at',
    ];

    protected $casts = [
        'input_context' => 'array',
        'raw_output' => 'array',
        'parsed_output' => 'array',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function step()
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    public function parentLog()
    {
        return $this->belongsTo(ExecutionLog::class, 'parent_execution_log_id');
    }

    public function childLogs()
    {
        return $this->hasMany(ExecutionLog::class, 'parent_execution_log_id');
    }
}
