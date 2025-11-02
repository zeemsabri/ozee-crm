<?php

namespace App\Models;

use App\Contracts\CreatableViaWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Context extends Model implements CreatableViaWorkflow
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'summary',
        'project_id',
        'referencable_type', 'referencable_id',
        'linkable_type', 'linkable_id',
        'user_id',
        'meta_data',
    ];

    protected $casts = [
        'meta_data' => 'array',
    ];

    public function referencable(): MorphTo
    {
        return $this->morphTo();
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function requiredOnCreate(): array
    {
        return [
            'summary',
            'referencable_type', 'referencable_id',
            'linkable_type', 'linkable_id',
        ];
    }

    public static function defaultsOnCreate(array $context): array
    {
        $out = [];
        // If this context is being created in response to an Email trigger,
        // provide sensible defaults for the polymorphic referencable.* pair.
        try {
            if (isset($context['trigger']) && is_array($context['trigger'])) {
                $trigger = $context['trigger'];
                if (isset($trigger['email']) && is_array($trigger['email'])) {
                    $email = $trigger['email'];
                    $id = $email['id'] ?? null;
                    if ($id) {
                        $out['referencable_type'] = \App\Models\Email::class;
                        $out['referencable_id'] = $id;
                    }
                }
            }
        } catch (\Throwable $e) { /* no-op */
        }

        return $out;
    }

    /**
     * Optional: Provide user-friendly labels, descriptions, and UI hints for workflow builder.
     */
    public static function fieldMetaForWorkflow(): array
    {
        return [
            'summary' => [
                'label' => 'Summary',
                'description' => 'A short description of what this Context is about.',
            ],
            'referencable_type' => [
                'label' => 'Reference Type',
                'description' => 'Choose what this item is referencing (e.g., Task, Project, Email).',
                'ui' => 'morph_type',
            ],
            'referencable_id' => [
                'label' => 'Reference Item',
                'description' => 'The ID of the item you selected above. Use a token like {{step_5.new_record_id}} when available.',
            ],
            'linkable_type' => [
                'label' => 'Link Type',
                'description' => 'Choose what this should be linked to (e.g., Task, Project, Email).',
                'ui' => 'morph_type',
            ],
            'linkable_id' => [
                'label' => 'Link Item',
                'description' => 'The ID of the item to link to. You can also insert a token.',
            ],
        ];
    }
}
