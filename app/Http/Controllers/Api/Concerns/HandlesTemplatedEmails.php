<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\PlaceholderDefinition;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

trait HandlesTemplatedEmails
{
    /**
     * Helper function to replace all placeholders in a string.
     *
     * @param string $content
     * @param EmailTemplate $template
     * @param array $templateData
     * @param mixed $recipient
     * @param Project $project
     * @param bool $isFinalSend
     * @return string
     */
    protected function populateAllPlaceholders(string $content, EmailTemplate $template, array $templateData, $recipient, Project $project, bool $isFinalSend): string
    {
        $replacements = [];
        $placeholders = $template->placeholders->keyBy('name');

        // Process dynamic data from the form
        foreach ($templateData as $key => $value) {
            $placeholder = $placeholders->get($key);
            if ($placeholder) {
                $placeholderTag = "{{ {$key} }}";
                $replacementValue = '';

                if ($placeholder->is_dynamic) {
                    $stringValue = is_array($value) ? '' : (string)($value ?? '');
                    if ($placeholder->is_link) {
                        $replacementValue = '<a href="' . e($stringValue) . '">' . e($key) . '</a>';
                    } else {
                        $replacementValue = $stringValue;
                    }
                } elseif ($placeholder->is_repeatable) {
                    if (is_array($value) && !empty($value) && $placeholder->source_model && $placeholder->source_attribute) {
                        $modelClass = $placeholder->source_model;
                        $attribute = $placeholder->source_attribute;
                        $items = $modelClass::whereIn('id', $value)->get();
                        $listHtml = '<ul>';
                        foreach ($items as $item) {
                            $listHtml .= '<li>' . ($item->{$attribute} ?? 'N/A') . '</li>';
                        }
                        $listHtml .= '</ul>';
                        $replacementValue = $listHtml;
                    }
                } elseif ($placeholder->is_selectable) {
                    if ($value && $placeholder->source_model && $placeholder->source_attribute) {
                        $modelClass = $placeholder->source_model;
                        $attribute = $placeholder->source_attribute;
                        $item = $modelClass::find($value);
                        $replacementValue = $item->{$attribute} ?? 'N/A';
                    }
                }

                $replacements[$placeholderTag] = $replacementValue;
            }
        }

        // Process static placeholders from the template
        foreach ($placeholders as $placeholder) {
            $placeholderTag = "{{ {$placeholder->name} }}";
            if (!isset($replacements[$placeholderTag])) {
                $replacements[$placeholderTag] = $this->getPlaceholderValue($placeholder, $recipient, $project, $isFinalSend);
            }
        }

        // Handle magic link button as a special case
        if (Str::contains($content, '{{ Magic Link Button }}')) {
            $magicLinkUrl = $this->getMagicLinkUrl($recipient->email, $project->id, $isFinalSend);
            $buttonHtml = '<a href="' . e($magicLinkUrl) . '" style="background-color:#5d50c6;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;font-size:16px;display:inline-block;box-shadow:0 4px 8px rgba(0,0,0,0.1);">Client Portal</a>';
            $content = str_replace('{{ Magic Link Button }}', $buttonHtml, $content);
        }

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return $content;
    }

    /**
     * Get the value for a static placeholder from its source model.
     *
     * @param PlaceholderDefinition $placeholder
     * @param mixed $recipient
     * @param Project $project
     * @param bool $isFinalSend
     * @return string
     */
    protected function getPlaceholderValue(PlaceholderDefinition $placeholder, $recipient, Project $project, bool $isFinalSend): string
    {
        if (!$placeholder->source_model || !$placeholder->source_attribute) {
            return 'N/A';
        }

        $modelClass = $placeholder->source_model;
        $attribute = $placeholder->source_attribute;

        if ($modelClass === 'App\\Models\\Client' && $recipient instanceof Client) {
            return $recipient->{$attribute} ?? 'N/A';
        }

        if ($modelClass === 'App\\Models\\User') {
            $user = Auth::user();
            if ($user) {
                return $user->{$attribute} ?? 'N/A';
            }
        }

        if ($modelClass === 'App\\Models\\Project') {
            return $project->{$attribute} ?? 'N/A';
        }

        if ($modelClass === 'App\\Models\\MagicLink' && $placeholder->name === 'Magic Link') {
            $magicLinkUrl = $this->getMagicLinkUrl($recipient->email, $project->id, $isFinalSend);
            return '<a href="' . e($magicLinkUrl) . '">Client Portal</a>';
        }

        return 'N/A';
    }

    /**
     * Get or generate a magic link URL based on the context.
     *
     * @param string $email
     * @param int $projectId
     * @param bool $isFinalSend
     * @return string
     */
    protected function getMagicLinkUrl(string $email, int $projectId, bool $isFinalSend): string
    {
        // For preview, return a placeholder URL to prevent database entries
        if (!$isFinalSend) {
            return '#preview_magic_link_url';
        }

        // Check for an existing, non-expired magic link to reuse it.
        $existingLink = $this->magicLinkService->getValidMagicLink($email, $projectId);
        if ($existingLink) {
            return URL::temporarySignedRoute(
                'client.magic-link-login',
                $existingLink->expires_at,
                ['token' => $existingLink->token]
            );
        }
        // If no valid link exists, generate a new one for the final send.
        return $this->magicLinkService->generateMagicLink($email, $projectId);
    }
}

