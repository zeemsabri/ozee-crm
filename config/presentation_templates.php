<?php

// config/presentation_templates.php

/**
 * ==================================================================================
 * SLIDE LIBRARY - Reusable functions to build consistent presentation templates.
 * ==================================================================================
 * This section contains helper functions that return standardized, generalized slide
 * structures. This allows for easy mixing and matching to create new templates.
 */

/**
 * Returns a standardized Intro Cover slide.
 * Ensures every presentation starts with a consistent, brand-aligned message.
 */
function getStandardIntroCover(): array
{
    return [
        'template_name' => 'IntroCover',
        'title' => 'Introduction',
        'display_order' => 1,
        'content_blocks' => [
            ['block_type' => 'image', 'content_data' => ['alt' => 'Company Logo', 'url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/Logo_simple_colour.png'], 'display_order' => 1],
            ['block_type' => 'heading', 'content_data' => ['text' => 'Your Partner in Digital Transformation', 'level' => 2], 'display_order' => 2],
            ['block_type' => 'paragraph', 'content_data' => ['text' => 'We are pleased to present this proposal for [Client Name], designed to build an efficient, scalable, and connected digital future for your business.'], 'display_order' => 3],
            ['block_type' => 'details_list', 'content_data' => ['items' => ['Prepared for: [Client Name]', 'Prepared by: [Your Name]', 'Document Number: [Document Number]']], 'display_order' => 4],
        ],
    ];
}

/**
 * Returns a standardized Call To Action slide.
 * Ensures every presentation ends with a clear and consistent next step.
 */
function getStandardCtaSlide(int $order): array
{
    return [
        'template_name' => 'CallToAction',
        'title' => 'Call to Action',
        'display_order' => $order,
        'content_blocks' => [
            ['block_type' => 'heading', 'content_data' => ['text' => 'Let\'s Build Your Future', 'level' => 1], 'display_order' => 1],
            ['block_type' => 'paragraph', 'content_data' => ['text' => 'We are confident this solution will transform your operations. We look forward to your confirmation to begin this exciting project.'], 'display_order' => 2],
            ['block_type' => 'slogan', 'content_data' => ['text' => 'Innovate. Optimize. Grow.'], 'display_order' => 3],
        ],
    ];
}

/**
 * Returns a generic "Challenge" slide with three feature cards.
 */
function getChallengeSlide(int $order, string $heading, array $challenges): array
{
    return [
        'template_name' => 'ThreeColumn',
        'title' => 'The Challenge',
        'display_order' => $order,
        'content_blocks' => [
            ['block_type' => 'heading', 'content_data' => ['text' => $heading, 'level' => 2], 'display_order' => 1],
            ['block_type' => 'feature_card', 'content_data' => $challenges[0], 'display_order' => 2],
            ['block_type' => 'feature_card', 'content_data' => $challenges[1], 'display_order' => 3],
            ['block_type' => 'feature_card', 'content_data' => $challenges[2], 'display_order' => 4],
        ],
    ];
}

/**
 * Returns a generic "Solution" slide with four feature cards.
 */
function getSolutionSlide(int $order, string $heading, string $paragraph, array $pillars): array
{
    return [
        'template_name' => 'FourColumn',
        'title' => 'Our Solution',
        'display_order' => $order,
        'content_blocks' => [
            ['block_type' => 'heading', 'content_data' => ['text' => $heading, 'level' => 2], 'display_order' => 1],
            ['block_type' => 'paragraph', 'content_data' => ['text' => $paragraph], 'display_order' => 2],
            ['block_type' => 'feature_card', 'content_data' => $pillars[0], 'display_order' => 3],
            ['block_type' => 'feature_card', 'content_data' => $pillars[1], 'display_order' => 4],
            ['block_type' => 'feature_card', 'content_data' => $pillars[2], 'display_order' => 5],
            ['block_type' => 'feature_card', 'content_data' => $pillars[3], 'display_order' => 6],
        ],
    ];
}

/**
 * Returns a generic "Service Detail" slide (TwoColumnWithImage).
 */
function getServiceDetailSlide(int $order, string $title, string $template, array $content): array
{
    return [
        'template_name' => $template, // 'TwoColumnWithImageRight' or 'TwoColumnWithImageLeft'
        'title' => $title,
        'display_order' => $order,
        'content_blocks' => [
            ['block_type' => 'heading', 'content_data' => ['text' => $content['heading'], 'level' => 2], 'display_order' => 1],
            ['block_type' => 'paragraph', 'content_data' => ['text' => $content['paragraph']], 'display_order' => 2],
            ['block_type' => 'list_with_icons', 'content_data' => ['items' => $content['items']], 'display_order' => 3],
            ['block_type' => 'image', 'content_data' => ['url' => $content['image_url'], 'alt' => $content['image_alt']], 'display_order' => 4],
        ],
    ];
}

/**
 * Returns a generic "Process" slide with four steps.
 */
function getProcessSlide(int $order, string $heading, array $steps): array
{
    return [
        'template_name' => 'FourStepProcess',
        'title' => 'Our Process',
        'display_order' => $order,
        'content_blocks' => [
            ['block_type' => 'heading', 'content_data' => ['text' => $heading, 'level' => 2], 'display_order' => 1],
            ['block_type' => 'step_card', 'content_data' => array_merge(['step_number' => 1], $steps[0]), 'display_order' => 2],
            ['block_type' => 'step_card', 'content_data' => array_merge(['step_number' => 2], $steps[1]), 'display_order' => 3],
            ['block_type' => 'step_card', 'content_data' => array_merge(['step_number' => 3], $steps[2]), 'display_order' => 4],
            ['block_type' => 'step_card', 'content_data' => array_merge(['step_number' => 4], $steps[3]), 'display_order' => 5],
        ],
    ];
}

/**
 * Returns a generic "Project Details" slide with pricing and timeline.
 */
function getProjectDetailsSlide(int $order, array $pricing, array $timeline): array
{
    return [
        'template_name' => 'ProjectDetails',
        'title' => 'Project Details',
        'display_order' => $order,
        'content_blocks' => [
            ['block_type' => 'heading', 'content_data' => ['text' => 'Investment & Timeline', 'level' => 2], 'display_order' => 1],
            ['block_type' => 'pricing_table', 'content_data' => $pricing, 'display_order' => 2],
            ['block_type' => 'timeline_table', 'content_data' => $timeline, 'display_order' => 3],
        ],
    ];
}

/**
 * Returns a generic "Why Us" slide.
 */
function getWhyUsSlide(int $order): array
{
    return [
        'template_name' => 'TwoColumnWithChart',
        'title' => 'Why Us',
        'display_order' => $order,
        'content_blocks' => [
            ['block_type' => 'heading', 'content_data' => ['text' => 'Why We\'re Your Ideal Partner', 'level' => 2], 'display_order' => 1],
            ['block_type' => 'feature_list', 'content_data' => ['items' => [
                ['title' => 'Commitment to Quality', 'description' => 'We offer unlimited revisions within the project scope to ensure your complete satisfaction.'],
                ['title' => 'Innovative & Scalable Solutions', 'description' => 'Our robust technology ensures your solution is performant and future-proof.'],
                ['title' => 'Reliable Support', 'description' => 'Enjoy 3 months of free support post-deployment, with ongoing maintenance options available.'],
            ]], 'display_order' => 2],
            ['block_type' => 'image_block', 'content_data' => ['url' => 'https://placehold.co/600x400/29438E/FFFFFF?text=Projected+Gains', 'title' => 'Projected Efficiency Gains'], 'display_order' => 3],
        ],
    ];
}


/**
 * ==================================================================================
 * PRESENTATION TEMPLATES - The final array of templates for the seeder.
 * ==================================================================================
 * Each template is an array of slides, built using the slide library functions.
 * Every template starts with the intro cover and ends with the call to action.
 */
return [
    'templates' => [

        // Template 1: A comprehensive proposal covering all services.
        // This template uses at least one of every slide type.
        [
            'title' => 'Full Digital Transformation Proposal',
            'slides' => [
                getStandardIntroCover(),
                getChallengeSlide(2, 'The Challenge: A Disconnected Digital Presence', [
                    ['icon' => 'fa-line-chart', 'title' => 'Stagnant Growth', 'description' => 'Difficulty attracting new leads and converting customers online.'],
                    ['icon' => 'fa-users', 'title' => 'Inefficient Operations', 'description' => 'Manual processes are consuming time and creating errors.'],
                    ['icon' => 'fa-mobile-alt', 'title' => 'Outdated Technology', 'description' => 'Your current website is not meeting modern user expectations.'],
                ]),
                getSolutionSlide(3, 'Our Integrated Solution', 'We propose a multi-faceted digital strategy to revitalize your online presence.', [
                    ['icon' => 'fa-laptop-code', 'title' => 'Web & Mobile Apps'],
                    ['icon' => 'fa-search', 'title' => 'SEO & Content'],
                    ['icon' => 'fa-share-alt', 'title' => 'Social & Digital Ads'],
                    ['icon' => 'fa-rocket', 'title' => 'Performance Optimization'],
                ]),
                getServiceDetailSlide(4, 'Web Application Development', 'TwoColumnWithImageRight', [
                    'heading' => 'Custom Web Application', 'paragraph' => 'Streamline your operations with a bespoke web application.',
                    'items' => ['Automated workflows to reduce manual tasks.', 'Secure customer data management.', 'Scalable architecture for future growth.'],
                    'image_url' => 'https://placehold.co/600x400/F7A823/FFFFFF?text=Web+App', 'image_alt' => 'Web Application Interface',
                ]),
                getServiceDetailSlide(5, 'Mobile Application Development', 'TwoColumnWithImageLeft', [
                    'heading' => 'Engaging Mobile App', 'paragraph' => 'Reach your customers anywhere with a native mobile application.',
                    'items' => ['iOS and Android development.', 'Push notifications for direct engagement.', 'Seamless user experience and design.'],
                    'image_url' => 'https://placehold.co/600x400/29438E/FFFFFF?text=Mobile+App', 'image_alt' => 'Mobile Application Interface',
                ]),
                getProcessSlide(6, 'Our Proven Development Process', [
                    ['title' => 'Discovery & Planning', 'description' => 'We start by understanding your goals and defining the project scope.'],
                    ['title' => 'Design & Prototyping', 'description' => 'Creating intuitive UI/UX designs and interactive prototypes.'],
                    ['title' => 'Development & Testing', 'description' => 'Building the application with rigorous quality assurance.'],
                    ['title' => 'Deployment & Support', 'description' => 'Launching the application with ongoing support and maintenance.'],
                ]),
                getWhyUsSlide(7),
                getProjectDetailsSlide(8,
                    ['price' => 'Custom Quote', 'title' => 'Investment', 'payment_schedule' => ['50% upon project confirmation', '50% upon project completion']],
                    ['title' => 'Estimated Timeline', 'timeline' => [['phase' => 'Phase 1: Web App', 'duration' => '12-16 Weeks'], ['phase' => 'Phase 2: SEO & Social', 'duration' => 'Ongoing'], ['phase' => 'Phase 3: Mobile App', 'duration' => '16-20 Weeks']]]
                ),
                getStandardCtaSlide(9),
            ],
        ],

        // Template 2: Website Redesign Proposal
        [
            'title' => 'Website Redesign Proposal',
            'slides' => [
                getStandardIntroCover(),
                getChallengeSlide(2, 'The Challenge: An Outdated Online Presence', [
                    ['icon' => 'fa-mobile-alt', 'title' => 'Poor Mobile Experience', 'description' => 'Your site is difficult to use on smartphones and tablets.'],
                    ['icon' => 'fa-eye-slash', 'title' => 'Low User Engagement', 'description' => 'High bounce rates and low time-on-page metrics.'],
                    ['icon' => 'fa-paint-brush', 'title' => 'Dated Visual Design', 'description' => 'The current design does not reflect your modern brand identity.'],
                ]),
                getServiceDetailSlide(3, 'UI/UX & Visual Redesign', 'TwoColumnWithImageRight', [
                    'heading' => 'Modern UI/UX Redesign', 'paragraph' => 'We will create a visually stunning and intuitive website experience.',
                    'items' => ['Mobile-first responsive design.', 'User-centric navigation and layout.', 'High-quality graphics and branding.'],
                    'image_url' => 'https://placehold.co/600x400/F7A823/FFFFFF?text=UI%2FUX+Design', 'image_alt' => 'UI/UX Design Process',
                ]),
                getProcessSlide(4, 'Our Redesign Process', [
                    ['title' => 'Audit & Strategy', 'description' => 'Analyzing your current site and defining redesign goals.'],
                    ['title' => 'Wireframing & Design', 'description' => 'Creating the new look and feel with your feedback.'],
                    ['title' => 'Development & Content', 'description' => 'Building the new site and migrating content.'],
                    ['title' => 'Launch & Optimization', 'description' => 'Deploying the new site and monitoring performance.'],
                ]),
                getProjectDetailsSlide(5,
                    ['price' => 'AUD 8,000 (+GST)', 'title' => 'Investment', 'payment_schedule' => ['50% upfront', '50% on completion']],
                    ['title' => 'Estimated Timeline', 'timeline' => [['phase' => 'Design', 'duration' => '2-3 Weeks'], ['phase' => 'Development', 'duration' => '4-6 Weeks'], ['phase' => 'Launch', 'duration' => '1 Week']]]
                ),
                getStandardCtaSlide(6),
            ],
        ],

        // Template 3: SEO & Content Marketing Proposal
        [
            'title' => 'SEO & Content Marketing Proposal',
            'slides' => [
                getStandardIntroCover(),
                getChallengeSlide(2, 'The Challenge: Low Online Visibility', [
                    ['icon' => 'fa-search-minus', 'title' => 'Poor Search Rankings', 'description' => 'Your website does not appear on the first page for key search terms.'],
                    ['icon' => 'fa-traffic-light', 'title' => 'Low Organic Traffic', 'description' => 'Struggling to attract qualified visitors from search engines.'],
                    ['icon' => 'fa-bullhorn', 'title' => 'Lack of Authority', 'description' => 'Your content doesn\'t establish you as an industry leader.'],
                ]),
                getSolutionSlide(3, 'Our SEO & Content Solution', 'A strategy to increase rankings, drive traffic, and build authority.', [
                    ['icon' => 'fa-wrench', 'title' => 'Technical SEO'],
                    ['icon' => 'fa-file-alt', 'title' => 'Content Creation'],
                    ['icon' => 'fa-link', 'title' => 'Link Building'],
                    ['icon' => 'fa-chart-bar', 'title' => 'Reporting'],
                ]),
                getProjectDetailsSlide(4,
                    ['price' => 'AUD 2,500/month (+GST)', 'title' => 'Monthly Retainer', 'payment_schedule' => ['Billed monthly', 'Minimum 6-month engagement']],
                    ['title' => 'Expected Timeline', 'timeline' => [['phase' => 'Initial Audit & Setup', 'duration' => 'Month 1'], ['phase' => 'Content & Outreach', 'duration' => 'Months 2-6'], ['phase' => 'Performance Review', 'duration' => 'Quarterly']]]
                ),
                getStandardCtaSlide(5),
            ],
        ],

        // Template 4: Social Media Management Proposal
        [
            'title' => 'Social Media Management Proposal',
            'slides' => [
                getStandardIntroCover(),
                getChallengeSlide(2, 'The Challenge: Inconsistent Social Presence', [
                    ['icon' => 'fa-comment-slash', 'title' => 'Low Engagement', 'description' => 'Your posts receive few likes, comments, or shares.'],
                    ['icon' => 'fa-users-slash', 'title' => 'Stagnant Follower Growth', 'description' => 'Struggling to attract and retain a relevant audience.'],
                    ['icon' => 'fa-calendar-times', 'title' => 'Inconsistent Posting', 'description' => 'An infrequent or haphazard posting schedule.'],
                ]),
                getServiceDetailSlide(3, 'Strategic Social Media Management', 'TwoColumnWithImageLeft', [
                    'heading' => 'Engage Your Audience', 'paragraph' => 'We will develop and execute a content strategy to grow your brand on social media.',
                    'items' => ['Monthly content calendar and scheduling.', 'Community management and engagement.', 'Performance analytics and reporting.'],
                    'image_url' => 'https://placehold.co/600x400/29438E/FFFFFF?text=Social+Media', 'image_alt' => 'Social Media Strategy',
                ]),
                getProjectDetailsSlide(4,
                    ['price' => 'AUD 1,800/month (+GST)', 'title' => 'Monthly Retainer', 'payment_schedule' => ['Billed monthly', '3-month initial term']],
                    ['title' => 'Service Timeline', 'timeline' => [['phase' => 'Strategy & Onboarding', 'duration' => 'Week 1-2'], ['phase' => 'Content Creation & Execution', 'duration' => 'Ongoing'], ['phase' => 'Monthly Reporting', 'duration' => '1st week of each month']]]
                ),
                getStandardCtaSlide(5),
            ],
        ],

    ],
];

