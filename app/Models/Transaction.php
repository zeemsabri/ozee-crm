<?php

namespace App\Models;

use App\Http\Controllers\Api\Concerns\HasFinancialCalculations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory, HasFinancialCalculations;

    protected $fillable = [
        'project_id',
        'description',
        'amount',
        'currency',
        'user_id',
        'hours_spent',
        'type',
        'is_paid',
        'payment_date',
        'transaction_type_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => 'string',
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

    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }
}
