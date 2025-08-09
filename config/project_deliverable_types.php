<?php

return [
    [
        'name' => 'Website Development',
        'fields' => [
            ['key' => 'pages', 'label' => 'Pages (comma-separated)', 'type' => 'array-text', 'placeholder' => 'e.g., Homepage, About, Contact'],
            ['key' => 'tech_stack', 'label' => 'Tech Stack (comma-separated)', 'type' => 'array-text', 'placeholder' => 'e.g., Vue.js, Laravel, Tailwind CSS'],
            ['key' => 'estimated_hours', 'label' => 'Estimated Hours', 'type' => 'number', 'placeholder' => ''],
            ['key' => 'sitemap_link', 'label' => 'Sitemap Link', 'type' => 'text', 'placeholder' => 'https://example.com/sitemap'],
        ],
    ],
    [
        'name' => 'Social Media Management',
        'fields' => [
            ['key' => 'posts_per_month', 'label' => 'Posts Per Month', 'type' => 'number', 'placeholder' => 'e.g., 15'],
            ['key' => 'platforms', 'label' => 'Platforms (comma-separated)', 'type' => 'array-text', 'placeholder' => 'e.g., Instagram, Facebook, LinkedIn'],
            ['key' => 'content_calendar_link', 'label' => 'Content Calendar Link', 'type' => 'text', 'placeholder' => 'https://example.com/calendar'],
        ],
    ],
    [
        'name' => 'SEO Services',
        'fields' => [
            ['key' => 'keywords', 'label' => 'Target Keywords (comma-separated)', 'type' => 'array-text', 'placeholder' => 'e.g., seo agency, digital marketing, website design'],
            ['key' => 'pages_optimized', 'label' => 'Pages to Optimize (comma-separated)', 'type' => 'array-text', 'placeholder' => 'e.g., homepage, services/seo'],
            ['key' => 'ranking_report_link', 'label' => 'Ranking Report Link', 'type' => 'text', 'placeholder' => 'https://example.com/ranking-report'],
        ],
    ],
    [
        'name' => 'Branding & Design',
        'fields' => [
            ['key' => 'deliverables', 'label' => 'Deliverables (comma-separated)', 'type' => 'array-text', 'placeholder' => 'e.g., Logo, Brand Guide, Color Palette'],
            ['key' => 'design_tools', 'label' => 'Design Tools (comma-separated)', 'type' => 'array-text', 'placeholder' => 'e.g., Figma, Adobe Illustrator'],
            ['key' => 'mood_board_link', 'label' => 'Mood Board Link', 'type' => 'text', 'placeholder' => 'https://dribbble.com/moodboard'],
        ],
    ],
    [
        'name' => 'Email Marketing',
        'fields' => [
            ['key' => 'campaigns_per_month', 'label' => 'Campaigns Per Month', 'type' => 'number', 'placeholder' => 'e.g., 4'],
            ['key' => 'audience_size', 'label' => 'Audience Size', 'type' => 'number', 'placeholder' => 'e.g., 5000'],
            ['key' => 'platform', 'label' => 'Platform', 'type' => 'text', 'placeholder' => 'e.g., Mailchimp, Klaviyo'],
        ],
    ],
    [
        'name' => 'General / Custom',
        'fields' => [
            ['key' => 'notes', 'label' => 'Key Notes', 'type' => 'textarea', 'placeholder' => 'Add any specific notes or details here...'],
        ],
    ],
];
