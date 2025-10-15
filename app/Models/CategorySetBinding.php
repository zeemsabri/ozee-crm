<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategorySetBinding extends Model
{
    use HasFactory;

    protected $fillable = ['category_set_id', 'model_type'];

    public function set()
    {
        return $this->belongsTo(CategorySet::class, 'category_set_id');
    }
}
