<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillPaymentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'contractor_id',
        'payment_method',
        'details',
    ];

    protected $casts = [
        'details' => 'encrypted:array',
    ];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function contractor()
    {
        return $this->belongsTo(User::class, 'contractor_id');
    }
}
