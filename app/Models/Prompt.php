<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'version',
        'system_prompt_text',
        'model_name',
        'generation_config',
        'template_variables',
        'status',
    ];

    protected $casts = [
        'generation_config' => 'array',
        'template_variables' => 'array',
    ];

    public function steps()
    {
        return $this->hasMany(WorkflowStep::class);
    }
}
