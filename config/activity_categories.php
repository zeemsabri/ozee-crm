<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activity Categories
    |--------------------------------------------------------------------------
    |
    | Define domain patterns and their corresponding categories for
    | automatic classification of user activity.
    |
    */

    'categories' => [
        'productive' => [
            'label' => 'Productive',
            'color' => '#10b981',
            'patterns' => [
                'wordpress.com', 'wordpress.org', 'wp-admin', 'wp-login',
                'figma.com', 'canva.com', 'adobe.com', 'sketch.com',
                'semrush.com', 'ahrefs.com', 'moz.com', 'screaming',
                'trello.com', 'asana.com', 'monday.com', 'notion.so',
                'clickup.com', 'basecamp.com', 'jira.atlassian.com',
                'analytics.google.com', 'tagmanager.google.com',
                'searchconsole.google.com', 'ads.google.com',
            ],
        ],

        'development' => [
            'label' => 'Development',
            'color' => '#3b82f6',
            'patterns' => [
                'github.com', 'gitlab.com', 'bitbucket.org',
                'stackoverflow.com', 'stackexchange.com',
                'laravel.com', 'vuejs.org', 'reactjs.org',
                'tailwindcss.com', 'getbootstrap.com',
                'developer.mozilla.org', 'w3schools.com',
                'npmjs.com', 'packagist.org', 'composer',
                'localhost', '127.0.0.1', '.test', '.local',
            ],
        ],

        'communication' => [
            'label' => 'Communication',
            'color' => '#8b5cf6',
            'patterns' => [
                'gmail.com', 'outlook.com', 'mail.google.com',
                'slack.com', 'teams.microsoft.com',
                'zoom.us', 'meet.google.com', 'webex.com',
                'discord.com', 'telegram.org',
            ],
        ],

        'social_media' => [
            'label' => 'Social Media',
            'color' => '#ec4899',
            'patterns' => [
                'facebook.com', 'fb.com', 'instagram.com',
                'twitter.com', 'x.com', 'linkedin.com',
                'tiktok.com', 'pinterest.com', 'snapchat.com',
                'reddit.com', 'youtube.com', 'vimeo.com',
                'whatsapp.com', 'messenger.com',
            ],
        ],

        'neutral' => [
            'label' => 'Neutral',
            'color' => '#6b7280',
            'patterns' => [
                'google.com', 'bing.com', 'duckduckgo.com',
                'wikipedia.org', 'docs.google.com',
                'drive.google.com', 'dropbox.com',
            ],
        ],

        'unproductive' => [
            'label' => 'Unproductive',
            'color' => '#ef4444',
            'patterns' => [
                'netflix.com', 'hulu.com', 'disneyplus.com',
                'twitch.tv', 'amazon.com', 'ebay.com',
                'aliexpress.com', 'shopping', 'games',
                'espn.com', 'sports', 'betting',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Productivity Score Weights
    |--------------------------------------------------------------------------
    |
    | Define how much each category contributes to the productivity score.
    | Values range from 0.0 (not productive) to 1.0 (fully productive).
    |
    */

    'productivity_weights' => [
        'productive' => 1.0,
        'development' => 1.0,
        'communication' => 0.8,
        'neutral' => 0.5,
        'social_media' => 0.2,
        'unproductive' => 0.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Category
    |--------------------------------------------------------------------------
    |
    | The category to assign when a domain doesn't match any patterns.
    |
    */

    'default_category' => 'neutral',
];
