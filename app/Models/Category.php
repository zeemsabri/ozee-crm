<?php

namespace App\Models;

use App\Contracts\CreatableViaWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model implements CreatableViaWorkflow
{
    use HasFactory;

    protected $fillable = ['name', 'category_set_id'];

    public function set()
    {
        return $this->belongsTo(CategorySet::class, 'category_set_id');
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
                'label' => 'Category Name',
                'description' => 'The display name for this category',
            ],
            'category_set_id' => [
                'label' => 'Category Set',
                'description' => 'The set this category belongs to',
            ],
        ];
    }
}
