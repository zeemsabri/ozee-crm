<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'xero_item_code',
        'default_amount',
        'default_currency',
        'default_frequency',
        'default_payment_breakdown',
        'default_description',
        'default_xero_account_code',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'default_payment_breakdown' => 'array',
    ];

    public function projectServices()
    {
        return $this->hasMany(ProjectService::class);
    }
}
