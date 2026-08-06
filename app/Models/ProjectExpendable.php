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

    /** @deprecated use App\Enums\ProjectExpendableStatus::Shortlisted */
    public const STATUS_SHORTLISTED = \App\Enums\ProjectExpendableStatus::Shortlisted->value;

    /** @deprecated use App\Enums\ProjectExpendableStatus::Rejected */
    public const STATUS_REJECTED = \App\Enums\ProjectExpendableStatus::Rejected->value;

    /** @deprecated use App\Enums\ProjectExpendableStatus::Completed */
    public const STATUS_COMPLETED = \App\Enums\ProjectExpendableStatus::Completed->value;

    protected $fillable = [
        'name',
        'description',
        'payment_terms',
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

    public function shortlist(?User $causer = null): void
    {
        $this->status = \App\Enums\ProjectExpendableStatus::Shortlisted;
        $this->save();

        activity('project_expendable')
            ->performedOn($this)
            ->causedBy($causer ?? auth()->user())
            ->withProperties(['status' => \App\Enums\ProjectExpendableStatus::Shortlisted->value])
            ->event('expendable.shortlisted')
            ->log("Expendable '{$this->name}' shortlisted");
    }

    public function unshortlist(?User $causer = null): void
    {
        $this->status = \App\Enums\ProjectExpendableStatus::PendingApproval;
        $this->save();

        activity('project_expendable')
            ->performedOn($this)
            ->causedBy($causer ?? auth()->user())
            ->withProperties(['status' => \App\Enums\ProjectExpendableStatus::PendingApproval->value])
            ->event('expendable.unshortlisted')
            ->log("Expendable '{$this->name}' moved back to pending");
    }

    public function complete(string $reason, ?User $causer = null): void
    {
        $this->status = \App\Enums\ProjectExpendableStatus::Completed;
        $this->save();

        activity('project_expendable')
            ->performedOn($this)
            ->causedBy($causer ?? auth()->user())
            ->withProperties(['reason' => $reason, 'status' => \App\Enums\ProjectExpendableStatus::Completed->value])
            ->event('expendable.completed')
            ->log("Expendable '{$this->name}' marked as completed");
    }

    public function checkCompletionStatus(): bool
    {
        $statusValue = is_string($this->status) ? $this->status : $this->status->value;
        if ($statusValue !== \App\Enums\ProjectExpendableStatus::Accepted->value) {
            return false;
        }

        $totalAmount = (float) $this->amount;
        if ($totalAmount <= 0) {
            return false;
        }

        $totalPaid = 0.0;
        foreach ($this->bills as $bill) {
            $totalPaid += (float) $bill->paid_amount;
        }

        if (round($totalPaid, 2) >= round($totalAmount, 2)) {
            $this->status = \App\Enums\ProjectExpendableStatus::Completed;
            $this->save();

            activity('project_expendable')
                ->performedOn($this)
                ->withProperties(['status' => \App\Enums\ProjectExpendableStatus::Completed->value])
                ->event('expendable.auto_completed')
                ->log("Expendable '{$this->name}' automatically marked as completed (paid in full).");

            return true;
        }

        return false;
    }

    public function bills()
    {
        return $this->hasMany(Bill::class, 'project_expendable_id');
    }

    /**
     * Accessor for expendable number (OZX + id).
     *
     * @return string
     */
    public function getExpendableNumberAttribute(): string
    {
        return 'OZX' . $this->id;
    }

    public function files()
    {
        return $this->morphMany(FileAttachment::class, 'fileable');
    }
}
