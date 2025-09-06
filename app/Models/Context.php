<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Context extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'summary',
        'referencable_type', 'referencable_id',
        'linkable_type', 'linkable_id',
        'user_id',
        'meta_data',
    ];

    protected $casts = [
        'meta_data' => 'array',
    ];

    public function referencable(): MorphTo
    {
        return $this->morphTo();
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
