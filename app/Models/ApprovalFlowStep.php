<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalFlowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_flow_id',
        'step_order',
        'approver_type',
        'approver_role_id',
        'approver_user_id',
        'label',
    ];

    public function flow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function approverRole()
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    public function approverUser()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
