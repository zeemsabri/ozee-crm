<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Project;
use App\Models\BonusConfiguration;

class BonusTransaction extends Model
{
    /** @deprecated use App\Enums\BonusTransactionStatus::Pending */
    public const STATUS_PENDING = \App\Enums\BonusTransactionStatus::Pending->value;
    /** @deprecated use App\Enums\BonusTransactionStatus::Approved */
    public const STATUS_APPROVED = \App\Enums\BonusTransactionStatus::Approved->value;
    /** @deprecated use App\Enums\BonusTransactionStatus::Rejected */
    public const STATUS_REJECTED = \App\Enums\BonusTransactionStatus::Rejected->value;
    /** @deprecated use App\Enums\BonusTransactionStatus::Processed */
    public const STATUS_PROCESSED = \App\Enums\BonusTransactionStatus::Processed->value;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'project_id',
        'bonus_configuration_id',
        'type',
        'amount',
        'description',
        'status',
        'source_type',
        'source_id',
        'processed_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'metadata' => 'array',
        'status' => \App\Enums\BonusTransactionStatus::class,
    ];

    /**
     * Get the user that owns the bonus transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the project that owns the bonus transaction.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the bonus configuration that triggered this transaction.
     */
    public function bonusConfiguration()
    {
        return $this->belongsTo(BonusConfiguration::class);
    }

    /**
     * Scope a query to only include transactions of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include transactions with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include transactions from a specific source.
     */
    public function scopeFromSource($query, $sourceType)
    {
        return $query->where('source_type', $sourceType);
    }
}
