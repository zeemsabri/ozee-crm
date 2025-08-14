<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ProjectExpendable extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    // Approval status constants
    public const STATUS_PENDING = 'Pending Approval';
    public const STATUS_ACCEPTED = 'Accepted';
    public const STATUS_REJECTED = 'Rejected';

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
        'status' => 'string',
        'currency' => 'string',
    ];

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->status)) {
                $model->status = self::STATUS_PENDING;
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
    public function accept(string $reason, User $causer = null): void
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->save();

        activity('project_expendable')
            ->performedOn($this)
            ->causedBy($causer ?? auth()->user())
            ->withProperties(['reason' => $reason, 'status' => self::STATUS_ACCEPTED])
            ->event('expendable.accepted')
            ->log("Expendable '{$this->name}' accepted");
    }

    public function reject(string $reason, User $causer = null): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->save();

        activity('project_expendable')
            ->performedOn($this)
            ->causedBy($causer ?? auth()->user())
            ->withProperties(['reason' => $reason, 'status' => self::STATUS_REJECTED])
            ->event('expendable.rejected')
            ->log("Expendable '{$this->name}' rejected");
    }


}
