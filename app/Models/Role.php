<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
    ];

    /**
     * The permissions that belong to the role.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * The users that belong to the role.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if the role has a specific permission.
     *
     * @param string $permissionSlug
     * @return bool
     */
    public function hasPermission($permissionSlug)
    {
        return $this->permissions()->where('slug', $permissionSlug)->exists();
    }

    /**
     * Assign a permission to the role.
     *
     * @param Permission|int $permission
     * @return void
     */
    public function assignPermission($permission)
    {
        if (is_numeric($permission)) {
            $permission = Permission::findOrFail($permission);
        }

        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    /**
     * Remove a permission from the role.
     *
     * @param Permission|int $permission
     * @return void
     */
    public function removePermission($permission)
    {
        if (is_numeric($permission)) {
            $permission = Permission::findOrFail($permission);
        }

        $this->permissions()->detach($permission);
    }
}
