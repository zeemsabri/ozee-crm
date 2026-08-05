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

    protected $appends = ['paid_amount', 'remaining_amount'];

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

    public function recalculateStatus(): void
    {
        if (!in_array($this->status, [BillStatus::Approved, BillStatus::Paid, BillStatus::PartialPaid])) {
            return;
        }

        $totalPaid = $this->paid_amount;
        $billAmount = (float) $this->amount;

        if (round($totalPaid, 2) >= round($billAmount, 2)) {
            $this->status = BillStatus::Paid;
        } elseif (round($totalPaid, 2) > 0) {
            $this->status = BillStatus::PartialPaid;
        } else {
            $this->status = BillStatus::Approved;
        }

        $this->save();
    }

    /**
     * Get the total amount paid towards this bill.
     */
    public function getPaidAmountAttribute(): float
    {
        $totalPaid = 0;
        $billCurrency = $this->currency ?? 'AUD';
        $conversionService = app(\App\Services\CurrencyConversionService::class);

        foreach ($this->transactions()->where('is_paid', true)->get() as $tx) {
            $txCurrency = $tx->currency ?? 'AUD';
            if ($txCurrency === $billCurrency) {
                $totalPaid += (float) $tx->amount;
            } else {
                if ($tx->exchange_rate && $tx->exchange_rate > 0) {
                    if ($txCurrency === 'AUD' && $billCurrency === 'PKR') {
                        $totalPaid += (float) $tx->amount * (float) $tx->exchange_rate;
                    } elseif ($txCurrency === 'PKR' && $billCurrency === 'AUD') {
                        $totalPaid += (float) $tx->amount / (float) $tx->exchange_rate;
                    } else {
                        $totalPaid += (float) $tx->amount * (float) $tx->exchange_rate;
                    }
                } else {
                    try {
                        $totalPaid += $conversionService->convert(
                            (float) $tx->amount,
                            $txCurrency,
                            $billCurrency
                        );
                    } catch (\Exception $e) {
                        $totalPaid += (float) $tx->amount;
                    }
                }
            }
        }
        return $totalPaid;
    }

    /**
     * Get the remaining unpaid amount for this bill.
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->amount - $this->paid_amount);
    }
}
