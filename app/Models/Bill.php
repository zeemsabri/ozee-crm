<?php

namespace App\Models;

use App\Enums\BillStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Bill extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'contractor_id',
        'project_id',
        'project_expendable_id',
        'transaction_type_id',
        'xero_account_code',
        'xero_tax_type',
        'amount',
        'status',
        'xero_invoice_id',
        'reference_number',
        'due_date',
        'currency',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => BillStatus::class,
        'due_date' => 'date',
    ];

    public function contractor()
    {
        return $this->belongsTo(User::class, 'contractor_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function expendable()
    {
        return $this->belongsTo(ProjectExpendable::class, 'project_expendable_id');
    }

    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function files()
    {
        return $this->morphMany(FileAttachment::class, 'fileable');
    }

    public function paymentDetail()
    {
        return $this->hasOne(BillPaymentDetail::class);
    }

    public function approvalInstance()
    {
        return $this->morphOne(ApprovalInstance::class, 'approvable');
    }

    /**
     * Accessor for bill number (OZB + id).
     *
     * @return string
     */
    public function getBillNumberAttribute(): string
    {
        return 'OZB' . $this->id;
    }
}
