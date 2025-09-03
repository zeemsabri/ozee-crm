<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Presentation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'presentable_id',
        'presentable_type',
        'title',
        'type',
        'share_token',
    ];

    protected $casts = [
    ];

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->share_token)) {
                $model->share_token = Str::random(64);
            }
        });
    }

    // Relations
    public function presentable()
    {
        return $this->morphTo();
    }

    public function metadata()
    {
        return $this->hasMany(PresentationMetadata::class);
    }

    public function slides()
    {
        return $this->hasMany(Slide::class)->orderBy('display_order');
    }
}
