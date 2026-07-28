<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'project_id',
        'total_amount',
        'status',
        'xero_invoice_id',
        'invoice_number',
        'currency',
        'line_amount_type',
        'xero_branding_theme_id',
        'xero_payment_service_ids',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'xero_payment_service_ids' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function comments()
    {
        return $this->hasMany(InvoiceComment::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function files()
    {
        return $this->morphMany(FileAttachment::class, 'fileable');
    }

    /**
     * Accessor for invoice number (OZI + id).
     *
     * @return string
     */
    public function getInvoiceNumberAttribute(): string
    {
        return 'OZI' . $this->id;
    }

    public function recalculateStatus(): void
    {
        if (!in_array($this->status, ['approved', 'sent', 'paid', 'partial_paid'])) {
            return;
        }

        $totalPaid = 0;
        $invoiceCurrency = $this->currency ?? 'AUD';
        $conversionService = app(\App\Services\CurrencyConversionService::class);

        foreach ($this->transactions()->where('is_paid', true)->get() as $tx) {
            $txCurrency = $tx->currency ?? 'AUD';
            try {
                $totalPaid += $conversionService->convert(
                    (float) $tx->amount,
                    $txCurrency,
                    $invoiceCurrency
                );
            } catch (\Exception $e) {
                $totalPaid += (float) $tx->amount;
            }
        }

        $invoiceAmount = (float) $this->total_amount;

        if (round($totalPaid, 2) >= round($invoiceAmount, 2)) {
            $this->status = 'paid';
        } elseif (round($totalPaid, 2) > 0) {
            $this->status = 'partial_paid';
        } else {
            $this->status = 'approved';
        }

        $this->save();
    }
}
