<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'notes',
    ];

    // Add a boot method to handle dynamic hiding
    protected static function boot()
    {
        parent::boot();

        static::retrieved(function ($user) {
            if (auth()->check() && ! auth()->user()->hasPermission('edit_clients')) {
                $user->setHidden(array_merge($user->getHidden(), ['email']));
            }
        });
    }

    // Or, a more explicit method you can call
    public function hideEmailIfUnauthorized()
    {
        if (auth()->check() && ! auth()->user()->hasPermission('edit_clients')) {
            $this->setHidden(array_merge($this->getHidden(), ['email']));
        }
        return $this;
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
}
