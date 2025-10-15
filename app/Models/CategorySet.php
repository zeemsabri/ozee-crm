<?php

namespace App\Models;

use App\Contracts\CreatableViaWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CategorySet extends Model implements CreatableViaWorkflow
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function bindings()
    {
        return $this->hasMany(CategorySetBinding::class);
    }

    protected static function booted()
    {
        static::creating(function (CategorySet $set) {
            if (empty($set->slug)) {
                $base = Str::slug($set->name);
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $set->slug = $slug;
            }
        });
    }

    public static function requiredOnCreate(): array
    {
        return ['name'];
    }

    public static function defaultsOnCreate(array $context): array
    {
        return [];
    }

    /**
     * Provide field metadata for workflow UI
     */
    public static function fieldMetaForWorkflow(): array
    {
        return [
            'name' => [
                'label' => 'Category Set Name',
                'description' => 'A descriptive name for this category set',
            ],
            'slug' => [
                'label' => 'URL Slug',
                'description' => 'Auto-generated URL-friendly identifier (leave blank to auto-generate)',
            ],
        ];
    }
}
