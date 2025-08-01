<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EmailTemplate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'subject',
        'body_html',
        'description',
        'is_default',
    ];

    /**
     * The placeholders that belong to the email template.
     */
    public function placeholders(): BelongsToMany
    {
        return $this->belongsToMany(PlaceholderDefinition::class, 'email_template_placeholder', 'email_template_id', 'placeholder_definition_id');
    }
}
