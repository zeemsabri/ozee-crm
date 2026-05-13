<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectService extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'enquiry_id',
        'service_id',
        'description',
        'amount',
        'currency',
        'frequency',
        'start_date',
        'payment_breakdown',
        'status',
        'service_tracking_type',
        'show_on_leads_board',
        'enquiry_status',
        'enquiry_created_at',
        'enquiry_updated_at',
        'enquiry_meta',
        'xero_account_code',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_breakdown' => 'array',
        'show_on_leads_board' => 'boolean',
        'enquiry_created_at' => 'datetime',
        'enquiry_updated_at' => 'datetime',
        'enquiry_meta' => 'array',
        'start_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
