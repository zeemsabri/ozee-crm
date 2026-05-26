<?php

namespace App\Models;

use App\Enums\BillStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contractor_id',
        'project_id',
        'project_expendable_id',
        'transaction_type_id',
        'amount',
        'status',
        'xero_invoice_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => BillStatus::class,
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
}
