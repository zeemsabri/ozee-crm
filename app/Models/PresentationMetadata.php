<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PresentationMetadata extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'presentation_metadata';

    protected $fillable = [
        'presentation_id',
        'meta_key',
        'meta_value',
    ];

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }
}
