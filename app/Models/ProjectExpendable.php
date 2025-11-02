<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectExpendable extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    // Approval status constants (kept as aliases for backward compatibility)
    /** @deprecated use App\Enums\ProjectExpendableStatus::PendingApproval */
    public const STATUS_PENDING = \App\Enums\ProjectExpendableStatus::PendingApproval->value;

    /** @deprecated use App\Enums\ProjectExpendableStatus::Accepted */
    public const STATUS_ACCEPTED = \App\Enums\ProjectExpendableStatus::Accepted->value;

    /** @deprecated use App\Enums\ProjectExpendableStatus::Rejected */
    public const STATUS_REJECTED = \App\Enums\ProjectExpendableStatus::Rejected->value;

    protected $fillable = [
        'name',
        'description',
        'project_id',
        'user_id',
        'currency',
        'amount',
        'balance',
        'status',
        'expendable_id',
        'expendable_type',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'status' => \App\Enums\ProjectExpendableStatus::class,
        'currency' => 'string',
    ];

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->status)) {
                $model->status = \App\Enums\ProjectExpendableStatus::PendingApproval;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('project_expendable')
            ->logOnly(['name', 'description', 'currency', 'amount', 'balance', 'status', 'project_id', 'user_id', 'expendable_id', 'expendable_type'])
            ->logOnlyDirty();
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function expendable()
    {
        return $this->morphTo();
    }

    // Actions
    public function accept(string $reason, ?User $causer = null): void
    {
        $this->status = \App\Enums\ProjectExpendableStatus::Accepted;
        $this->save();

        activity('project_expendable')
            ->performedOn($this)
            ->causedBy($causer ?? auth()->user())
            ->withProperties(['reason' => $reason, 'status' => \App\Enums\ProjectExpendableStatus::Accepted->value])
            ->event('expendable.accepted')
            ->log("Expendable '{$this->name}' accepted");
    }

    public function reject(string $reason, ?User $causer = null): void
    {
        $this->status = \App\Enums\ProjectExpendableStatus::Rejected;
        $this->save();

        activity('project_expendable')
            ->performedOn($this)
            ->causedBy($causer ?? auth()->user())
            ->withProperties(['reason' => $reason, 'status' => \App\Enums\ProjectExpendableStatus::Rejected->value])
            ->event('expendable.rejected')
            ->log("Expendable '{$this->name}' rejected");
    }
}
