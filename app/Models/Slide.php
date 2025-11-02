<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Slide extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'presentation_id',
        'template_name',
        'title',
        'display_order',
    ];

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }

    public function contentBlocks()
    {
        return $this->hasMany(ContentBlock::class)->orderBy('display_order');
    }
}
