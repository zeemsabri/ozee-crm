<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentBlock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slide_id',
        'block_type',
        'content_data',
        'display_order',
    ];

    protected $casts = [
        'content_data' => 'array',
    ];

    public function slide()
    {
        return $this->belongsTo(Slide::class);
    }
}
