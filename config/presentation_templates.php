<?php

// config/presentation_templates.php

/**
 * ==================================================================================
 * PRESENTATION CONFIGURATION
 * ==================================================================================
 * This file serves as the single source of truth for generating presentations.
 *
 * It defines:
 * 1.  'slide_blueprints': Reusable structures for each type of slide, complete
 * with placeholder content for dynamic generation.
 *
 * 2.  'generator_pool': A list of slide blueprint keys that the random generator
 * is allowed to use for creating the middle slides of a presentation.
 *
 * 3.  'templates': A list of predefined presentation templates for the seeder.
 * Each template is an array of slides that references a blueprint and
 * overrides its content with specific data.
 */

return [

    /**
     * ============================================================================
     * SLIDE BLUEPRINTS
     * ============================================================================
     * Define the structure and default placeholder content for every slide type.
     * The `key` (e.g., 'intro_cover') is used for referencing.
     */
    'slide_blueprints' => [

        'intro_cover' => [
            'template_name' => 'IntroCover',
            'title' => 'Introduction',
            'content_blocks' => [
                ['block_type' => 'image', 'content_data' => ['alt' => 'Company Logo', 'url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/Logo_simple_colour.png']],
                ['block_type' => 'heading', 'content_data' => ['text' => 'Your Partner in Digital Transformation', 'level' => 2]],
                ['block_type' => 'paragraph', 'content_data' => ['text' => 'We are pleased to present this proposal for [Client Name], designed to build an efficient, scalable, and connected digital future for your business.']],
                ['block_type' => 'details_list', 'content_data' => ['items' => ['Prepared for: [Client Name]', 'Prepared by: [Your Name]', 'Document Number: [Document Number]']]],
            ],
        ],

        'challenge' => [
            'template_name' => 'ThreeColumn',
            'title' => 'The Challenge',
            'content_blocks' => [
                ['block_type' => 'heading', 'content_data' => ['text' => 'Key Challenges We\'ve Identified', 'level' => 2]],
                ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-puzzle-piece', 'title' => 'Challenge One', 'description' => 'A brief description of the first challenge.']],
                ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-puzzle-piece', 'title' => 'Challenge Two', 'description' => 'A brief description of the second challenge.']],
                ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-puzzle-piece', 'title' => 'Challenge Three', 'description' => 'A brief description of the third challenge.']],
            ],
        ],

        'solution' => [
            'template_name' => 'FourColumn',
            'title' => 'Our Solution',
            'content_blocks' => [
                ['block_type' => 'heading', 'content_data' => ['text' => 'A Tailored Solution', 'level' => 2]],
                ['block_type' => 'paragraph', 'content_data' => ['text' => 'Our strategy is built on key pillars to address your needs.']],
                ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-lightbulb', 'title' => 'Pillar One']],
                ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-lightbulb', 'title' => 'Pillar Two']],
                ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-lightbulb', 'title' => 'Pillar Three']],
                ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-lightbulb', 'title' => 'Pillar Four']],
            ],
        ],

        'service_detail' => [
            'template_name' => 'TwoColumnWithImageRight',
            'title' => 'Core Service Detail',
            'content_blocks' => [
                ['block_type' => 'heading', 'content_data' => ['text' => 'A Core Service Offering', 'level' => 2]],
                ['block_type' => 'paragraph', 'content_data' => ['text' => 'A detailed explanation of a key service we provide.']],
                ['block_type' => 'list_with_icons', 'content_data' => ['items' => ['Key feature or benefit one.', 'Key feature or benefit two.', 'Key feature or benefit three.']]],
                ['block_type' => 'image', 'content_data' => ['url' => 'https://placehold.co/600x400/29438E/FFFFFF?text=Service+Detail', 'alt' => 'Service Detail Image']],
            ],
        ],

        'process' => [
            'template_name' => 'FourStepProcess',
            'title' => 'Our Process',
            'content_blocks' => [
                ['block_type' => 'heading', 'content_data' => ['text' => 'Our Strategic Process', 'level' => 2]],
                ['block_type' => 'step_card', 'content_data' => ['step_number' => 1, 'title' => 'Step 1: Discovery', 'description' => 'Understanding your unique goals and requirements.']],
                ['block_type' => 'step_card', 'content_data' => ['step_number' => 2, 'title' => 'Step 2: Strategy', 'description' => 'Developing a data-driven plan for success.']],
                ['block_type' => 'step_card', 'content_data' => ['step_number' => 3, 'title' => 'Step 3: Execution', 'description' => 'Implementing the strategy with precision and expertise.']],
                ['block_type' => 'step_card', 'content_data' => ['step_number' => 4, 'title' => 'Step 4: Analysis', 'description' => 'Measuring results and optimizing for continuous improvement.']],
            ],
        ],

        'why_us' => [
            'template_name' => 'TwoColumnWithChart',
            'title' => 'Why Us',
            'content_blocks' => [
                ['block_type' => 'heading', 'content_data' => ['text' => 'Why We\'re Your Ideal Partner', 'level' => 2]],
                ['block_type' => 'feature_list', 'content_data' => ['items' => [
                    ['title' => 'Commitment to Quality', 'description' => 'We offer unlimited revisions within the project scope to ensure your complete satisfaction.'],
                    ['title' => 'Innovative & Scalable Solutions', 'description' => 'Our robust technology ensures your solution is performant and future-proof.'],
                    ['title' => 'Reliable Support', 'description' => 'Enjoy 3 months of free support post-deployment, with ongoing maintenance options available.'],
                ]]],
                ['block_type' => 'image_block', 'content_data' => ['url' => 'https://placehold.co/600x400/29438E/FFFFFF?text=Projected+Gains', 'title' => 'Projected Efficiency Gains']],
            ],
        ],

        'project_details' => [
            'template_name' => 'ProjectDetails',
            'title' => 'Project Details',
            'content_blocks' => [
                ['block_type' => 'heading', 'content_data' => ['text' => 'Investment & Timeline', 'level' => 2]],
                ['block_type' => 'pricing_table', 'content_data' => ['price' => 'To Be Determined', 'title' => 'Investment', 'payment_schedule' => ['Payment terms to be discussed.']]],
                ['block_type' => 'timeline_table', 'content_data' => ['title' => 'Estimated Timeline', 'timeline' => [['phase' => 'Project Phase 1', 'duration' => 'X Weeks'], ['phase' => 'Project Phase 2', 'duration' => 'Y Weeks']]]],
            ],
        ],

        'call_to_action' => [
            'template_name' => 'CallToAction',
            'title' => 'Call to Action',
            'content_blocks' => [
                ['block_type' => 'heading', 'content_data' => ['text' => 'Let\'s Build Your Future', 'level' => 1]],
                ['block_type' => 'paragraph', 'content_data' => ['text' => 'We are confident this solution will transform your operations. We look forward to your confirmation to begin this exciting project.']],
                ['block_type' => 'slogan', 'content_data' => ['text' => 'Innovate. Optimize. Grow.']],
                ['block_type' => 'button', 'content_data' => ['text' => 'Accept & Proceed', 'action' => 'show_contact_form']],
            ],
        ],
    ],


    /**
     * ============================================================================
     * GENERATOR POOL
     * ============================================================================
     * The list of blueprint keys the generator can pick from for middle slides.
     */
    'generator_pool' => [
        'challenge',
        'solution',
        'service_detail',
        'process',
        'why_us',
        'project_details',
    ],


    /**
     * ============================================================================
     * SEEDER TEMPLATES
     * ============================================================================
     * Each template is built from slide blueprints, overriding content as needed.
     */
    'templates' => [
        // Template 1: Full Digital Transformation Proposal
        [
            'title' => 'Full Digital Transformation Proposal',
            'slides' => [
                ['blueprint' => 'intro_cover'],
                [
                    'blueprint' => 'challenge',
                    'overrides' => [
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'The Challenge: A Disconnected Digital Presence']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-line-chart', 'title' => 'Stagnant Growth', 'description' => 'Difficulty attracting new leads and converting customers online.']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-users', 'title' => 'Inefficient Operations', 'description' => 'Manual processes are consuming time and creating errors.']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-mobile-alt', 'title' => 'Outdated Technology', 'description' => 'Your current website is not meeting modern user expectations.']],
                        ],
                    ],
                ],
                [
                    'blueprint' => 'solution',
                    'overrides' => [
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'Our Integrated Solution']],
                            ['block_type' => 'paragraph', 'content_data' => ['text' => 'We propose a multi-faceted digital strategy to revitalize your online presence.']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-laptop-code', 'title' => 'Web & Mobile Apps']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-search', 'title' => 'SEO & Content']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-share-alt', 'title' => 'Social & Digital Ads']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-rocket', 'title' => 'Performance Optimization']],
                        ],
                    ],
                ],
                [
                    'blueprint' => 'service_detail',
                    'overrides' => [
                        'title' => 'Web Application Development',
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'Custom Web Application']],
                            ['block_type' => 'paragraph', 'content_data' => ['text' => 'Streamline your operations with a bespoke web application.']],
                            ['block_type' => 'list_with_icons', 'content_data' => ['items' => ['Automated workflows to reduce manual tasks.', 'Secure customer data management.', 'Scalable architecture for future growth.']]],
                            ['block_type' => 'image', 'content_data' => ['url' => 'https://placehold.co/600x400/F7A823/FFFFFF?text=Web+App', 'alt' => 'Web Application Interface']],
                        ],
                    ],
                ],
                [
                    'blueprint' => 'service_detail',
                    'overrides' => [
                        'title' => 'Mobile Application Development',
                        'template_name' => 'TwoColumnWithImageLeft',
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'Engaging Mobile App']],
                            ['block_type' => 'paragraph', 'content_data' => ['text' => 'Reach your customers anywhere with a native mobile application.']],
                            ['block_type' => 'list_with_icons', 'content_data' => ['items' => ['iOS and Android development.', 'Push notifications for direct engagement.', 'Seamless user experience and design.']]],
                            ['block_type' => 'image', 'content_data' => ['url' => 'https://placehold.co/600x400/29438E/FFFFFF?text=Mobile+App', 'alt' => 'Mobile Application Interface']],
                        ],
                    ],
                ],
                ['blueprint' => 'process', 'overrides' => ['title' => 'Our Proven Development Process']],
                ['blueprint' => 'why_us'],
                [
                    'blueprint' => 'project_details',
                    'overrides' => [
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'Investment & Timeline']],
                            ['block_type' => 'pricing_table', 'content_data' => ['price' => 'Custom Quote', 'title' => 'Investment', 'payment_schedule' => ['50% upon project confirmation', '50% upon project completion']]],
                            ['block_type' => 'timeline_table', 'content_data' => ['title' => 'Estimated Timeline', 'timeline' => [['phase' => 'Phase 1: Web App', 'duration' => '12-16 Weeks'], ['phase' => 'Phase 2: SEO & Social', 'duration' => 'Ongoing'], ['phase' => 'Phase 3: Mobile App', 'duration' => '16-20 Weeks']]]],
                        ],
                    ],
                ],
                ['blueprint' => 'call_to_action'],
            ],
        ],

        // Template 2: Website Redesign Proposal
        [
            'title' => 'Website Redesign Proposal',
            'slides' => [
                ['blueprint' => 'intro_cover'],
                [
                    'blueprint' => 'challenge',
                    'overrides' => [
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'The Challenge: An Outdated Online Presence']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-mobile-alt', 'title' => 'Poor Mobile Experience', 'description' => 'Your site is difficult to use on smartphones and tablets.']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-eye-slash', 'title' => 'Low User Engagement', 'description' => 'High bounce rates and low time-on-page metrics.']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-paint-brush', 'title' => 'Dated Visual Design', 'description' => 'The current design does not reflect your modern brand identity.']],
                        ],
                    ]
                ],
                [
                    'blueprint' => 'service_detail',
                    'overrides' => [
                        'title' => 'UI/UX & Visual Redesign',
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'Modern UI/UX Redesign']],
                            ['block_type' => 'paragraph', 'content_data' => ['text' => 'We will create a visually stunning and intuitive website experience.']],
                            ['block_type' => 'list_with_icons', 'content_data' => ['items' => ['Mobile-first responsive design.', 'User-centric navigation and layout.', 'High-quality graphics and branding.']]],
                            ['block_type' => 'image', 'content_data' => ['url' => 'https://placehold.co/600x400/F7A823/FFFFFF?text=UI%2FUX+Design', 'alt' => 'UI/UX Design Process']],
                        ]
                    ]
                ],
                ['blueprint' => 'process', 'overrides' => ['title' => 'Our Redesign Process']],
                [
                    'blueprint' => 'project_details',
                    'overrides' => [
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'Investment & Timeline']],
                            ['block_type' => 'pricing_table', 'content_data' => ['price' => 'AUD 8,000 (+GST)', 'title' => 'Investment', 'payment_schedule' => ['50% upfront', '50% on completion']]],
                            ['block_type' => 'timeline_table', 'content_data' => ['title' => 'Estimated Timeline', 'timeline' => [['phase' => 'Design', 'duration' => '2-3 Weeks'], ['phase' => 'Development', 'duration' => '4-6 Weeks'], ['phase' => 'Launch', 'duration' => '1 Week']]]],
                        ],
                    ]
                ],
                ['blueprint' => 'call_to_action'],
            ],
        ],

        // Template 3: SEO & Content Marketing Proposal
        [
            'title' => 'SEO & Content Marketing Proposal',
            'slides' => [
                ['blueprint' => 'intro_cover'],
                [
                    'blueprint' => 'challenge',
                    'overrides' => [
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'The Challenge: Low Online Visibility']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-search-minus', 'title' => 'Poor Search Rankings', 'description' => 'Your website does not appear on the first page for key search terms.']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-traffic-light', 'title' => 'Low Organic Traffic', 'description' => 'Struggling to attract qualified visitors from search engines.']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-bullhorn', 'title' => 'Lack of Authority', 'description' => 'Your content doesn\'t establish you as an industry leader.']],
                        ],
                    ],
                ],
                [
                    'blueprint' => 'solution',
                    'overrides' => [
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'Our SEO & Content Solution']],
                            ['block_type' => 'paragraph', 'content_data' => ['text' => 'A strategy to increase rankings, drive traffic, and build authority.']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-wrench', 'title' => 'Technical SEO']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-file-alt', 'title' => 'Content Creation']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-link', 'title' => 'Link Building']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-chart-bar', 'title' => 'Reporting']],
                        ],
                    ],
                ],
                [
                    'blueprint' => 'project_details',
                    'overrides' => [
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'Investment & Timeline']],
                            ['block_type' => 'pricing_table', 'content_data' => ['price' => 'AUD 2,500/month (+GST)', 'title' => 'Monthly Retainer', 'payment_schedule' => ['Billed monthly', 'Minimum 6-month engagement']]],
                            ['block_type' => 'timeline_table', 'content_data' => ['title' => 'Expected Timeline', 'timeline' => [['phase' => 'Initial Audit & Setup', 'duration' => 'Month 1'], ['phase' => 'Content & Outreach', 'duration' => 'Months 2-6'], ['phase' => 'Performance Review', 'duration' => 'Quarterly']]]],
                        ],
                    ],
                ],
                ['blueprint' => 'call_to_action'],
            ],
        ],

        // Template 4: Social Media Management Proposal
        [
            'title' => 'Social Media Management Proposal',
            'slides' => [
                ['blueprint' => 'intro_cover'],
                [
                    'blueprint' => 'challenge',
                    'overrides' => [
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'The Challenge: Inconsistent Social Presence']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-comment-slash', 'title' => 'Low Engagement', 'description' => 'Your posts receive few likes, comments, or shares.']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-users-slash', 'title' => 'Stagnant Follower Growth', 'description' => 'Struggling to attract and retain a relevant audience.']],
                            ['block_type' => 'feature_card', 'content_data' => ['icon' => 'fa-calendar-times', 'title' => 'Inconsistent Posting', 'description' => 'An infrequent or haphazard posting schedule.']],
                        ],
                    ],
                ],
                [
                    'blueprint' => 'service_detail',
                    'overrides' => [
                        'title' => 'Strategic Social Media Management',
                        'template_name' => 'TwoColumnWithImageLeft',
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'Engage Your Audience']],
                            ['block_type' => 'paragraph', 'content_data' => ['text' => 'We will develop and execute a content strategy to grow your brand on social media.']],
                            ['block_type' => 'list_with_icons', 'content_data' => ['items' => ['Monthly content calendar and scheduling.', 'Community management and engagement.', 'Performance analytics and reporting.']]],
                            ['block_type' => 'image', 'content_data' => ['url' => 'https://placehold.co/600x400/29438E/FFFFFF?text=Social+Media', 'alt' => 'Social Media Strategy']],
                        ],
                    ],
                ],
                [
                    'blueprint' => 'project_details',
                    'overrides' => [
                        'content_blocks' => [
                            ['block_type' => 'heading', 'content_data' => ['text' => 'Investment & Timeline']],
                            ['block_type' => 'pricing_table', 'content_data' => ['price' => 'AUD 1,800/month (+GST)', 'title' => 'Monthly Retainer', 'payment_schedule' => ['Billed monthly', '3-month initial term']]],
                            ['block_type' => 'timeline_table', 'content_data' => ['title' => 'Service Timeline', 'timeline' => [['phase' => 'Strategy & Onboarding', 'duration' => 'Week 1-2'], ['phase' => 'Content Creation & Execution', 'duration' => 'Ongoing'], ['phase' => 'Monthly Reporting', 'duration' => '1st week of each month']]]],
                        ],
                    ],
                ],
                ['blueprint' => 'call_to_action'],
            ],
        ],
    ],
];

