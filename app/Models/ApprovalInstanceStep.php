<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalInstanceStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_instance_id',
        'step_order',
        'approver_type',
        'approver_role_id',
        'approver_user_id',
        'label',
        'status',
        'acted_by_user_id',
        'acted_at',
        'comment',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function instance()
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

    public function approverRole()
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    public function approverUser()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function actedBy()
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }
}
