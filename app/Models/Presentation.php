<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Presentation extends Model
{
    use HasFactory, SoftDeletes;

    const PROPOSAL = 'proposal';

    const PRESENTATION = 'presentation';

    const AUDIT_REPORT = 'audit_report';

    const QUALIFIED = 'qualified';

    const NEW = 'new';

    const CONTACTED = 'contacted';

    const CONVERTED = 'converted';

    const LOST = 'lost';

    protected $fillable = [
        'presentable_id',
        'presentable_type',
        'title',
        'type',
        'share_token',
        'is_template',
    ];

    protected $casts = [
        'is_template' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->share_token)) {
                $model->share_token = Str::random(64);
            }
        });

        static::created(function (self $model) {
            $model->users()->attach(Auth::id(), ['role' => self::CONTACTED]);
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

    public function users()
    {
        return $this->belongsToMany(User::class, 'presentation_user')
            ->withPivot('role')
            ->withTimestamps();
    }
}
