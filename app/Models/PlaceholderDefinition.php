<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlaceholderDefinition extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'source_model',
        'source_attribute',
        'is_dynamic',
        'is_repeatable',
        'is_link',
        'is_selectable',
    ];

    /**
     * The email templates that use this placeholder.
     */
    public function emailTemplates(): BelongsToMany
    {
        return $this->belongsToMany(EmailTemplate::class, 'email_template_placeholder', 'placeholder_definition_id', 'email_template_id');
    }
}
