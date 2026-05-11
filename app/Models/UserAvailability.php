<?php

namespace App\Models;

use App\Models\Traits\HasCategories;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAvailability extends Model
{
    use HasCategories, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_availabilities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'is_available',
        'did_not_show_up',
        'was_late',
        'left_early',
        'did_not_show_up_reason_category_id',
        'was_late_reason_category_id',
        'left_early_reason_category_id',
        'actual_start_time',
        'actual_end_time',
        'reason',
        'time_slots',
        'admin_comments',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'is_available' => 'boolean',
        'did_not_show_up' => 'boolean',
        'was_late' => 'boolean',
        'left_early' => 'boolean',
        'time_slots' => 'array',
    ];

    protected $appends = [
        'did_not_show_up_reason',
        'was_late_reason',
        'left_early_reason',
    ];

    /**
     * Get the user that owns the availability.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDidNotShowUpReasonAttribute(): ?array
    {
        if (!$this->did_not_show_up_reason_category_id) {
            return null;
        }
        
        $category = $this->relationLoaded('categories')
            ? $this->categories->firstWhere('id', $this->did_not_show_up_reason_category_id)
            : Category::find($this->did_not_show_up_reason_category_id);

        return $category ? ['id' => $category->id, 'name' => $category->name] : null;
    }

    public function getWasLateReasonAttribute(): ?array
    {
        if (!$this->was_late_reason_category_id) {
            return null;
        }
        
        $category = $this->relationLoaded('categories')
            ? $this->categories->firstWhere('id', $this->was_late_reason_category_id)
            : Category::find($this->was_late_reason_category_id);

        return $category ? ['id' => $category->id, 'name' => $category->name] : null;
    }

    public function getLeftEarlyReasonAttribute(): ?array
    {
        if (!$this->left_early_reason_category_id) {
            return null;
        }
        
        $category = $this->relationLoaded('categories')
            ? $this->categories->firstWhere('id', $this->left_early_reason_category_id)
            : Category::find($this->left_early_reason_category_id);

        return $category ? ['id' => $category->id, 'name' => $category->name] : null;
    }
}
