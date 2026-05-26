<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_flow_id',
        'approvable_type',
        'approvable_id',
        'current_step_order',
        'status',
    ];

    public function flow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function approvable()
    {
        return $this->morphTo();
    }

    public function steps()
    {
        return $this->hasMany(ApprovalInstanceStep::class)->orderBy('step_order');
    }

    public function currentPendingStep()
    {
        return $this->steps()->where('status', 'pending')->orderBy('step_order')->first();
    }
}
