<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'title',
        'status',
        'source',
        'pipeline_stage',
        'estimated_value',
        'currency',
        'assigned_to_id',
        'created_by_id',
        'contacted_at',
        'converted_at',
        'lost_reason',
        'website',
        'country',
        'state',
        'city',
        'address',
        'zip',
        'tags',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'contacted_at' => 'datetime',
        'converted_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Relationships
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    // Scopes
    public function scopeStatus($query, ?string $status)
    {
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }
        return $query;
    }

    public function scopeSource($query, ?string $source)
    {
        if ($source !== null && $source !== '') {
            $query->where('source', $source);
        }
        return $query;
    }

    public function scopeAssignedTo($query, $userId)
    {
        if (!empty($userId)) {
            $query->where('assigned_to_id', $userId);
        }
        return $query;
    }

    public function scopeSearch($query, ?string $q)
    {
        if ($q !== null && $q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhereRaw("concat(coalesce(first_name,''),' ',coalesce(last_name,'')) like ?", ["%{$q}%"])
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%");
            });
        }
        return $query;
    }
}
