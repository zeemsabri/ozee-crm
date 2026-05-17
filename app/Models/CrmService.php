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
    ];

    public function projectServices()
    {
        return $this->hasMany(ProjectService::class);
    }
}
