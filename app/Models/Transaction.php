<?php

namespace App\Models;

use App\Http\Controllers\Api\Concerns\HasFinancialCalculations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, HasFinancialCalculations, SoftDeletes;

    protected $fillable = [
        'project_id',
        'description',
        'amount',
        'currency',
        'exchange_rate',
        'user_id',
        'client_id',
        'hours_spent',
        'type',
        'is_paid',
        'payment_date',
        'transaction_type_id',
        'bill_id',
        'invoice_id',
        'bank_transaction_id',
        'xero_payment_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => 'string',
        'exchange_rate' => 'decimal:6',
        'hours_spent' => 'decimal:2',
        'type' => 'string',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function files()
    {
        return $this->morphMany(FileAttachment::class, 'fileable');
    }
}
