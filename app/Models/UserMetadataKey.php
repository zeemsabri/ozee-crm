<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMetadataKey extends Model
{
    protected $fillable = ['key', 'label', 'type'];
}
