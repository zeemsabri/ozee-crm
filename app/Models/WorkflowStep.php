<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'workflow_id',
        'step_order',
        'name',
        'step_type',
        'prompt_id',
        'step_config',
        'condition_rules',
        'delay_minutes',
    ];

    protected $casts = [
        'step_config' => 'array',
        'condition_rules' => 'array',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function prompt()
    {
        return $this->belongsTo(Prompt::class);
    }
}
