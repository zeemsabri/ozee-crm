<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Presentation;
use App\Models\Slide;
use App\Models\ContentBlock;

class PresentationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Sample Lead for testing purposes
        $lead = Lead::first() ?? Lead::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'company' => 'Future Co',
        ]);

        // Define template data arrays
        $templates = [
            $this->getDynamicOutdoorProposalTemplateData(),
            $this->getSEOAuditReportTemplateData(),
            $this->getMarketingCampaignProposalTemplateData(),
            $this->getEcommerceDevelopmentProposalTemplateData(),
        ];

        foreach ($templates as $templateData) {
            $this->createTemplate($lead, $templateData);
        }

        $this->command->info('Template creation complete.');
    }

    /**
     * Create a presentation template with slides and content blocks.
     *
     * @param Lead $lead
     * @param array $data
     * @return void
     */
    private function createTemplate(Lead $lead, array $data): void
    {
        $template = $lead->presentations()->updateOrCreate([
            'title' => $data['title'],
            'type'  =>  Presentation::PROPOSAL,
            'is_template' => true,
        ]);

        // If the template already exists, delete its slides and content blocks
        if ($template->exists) {
            $template->slides()->delete();
        }
        $template->save();

        $this->command->info('Creating ' . $data['title'] . '...');

        foreach ($data['slides'] as $slideData) {
            $slide = $template->slides()->create([
                'template_name' => $slideData['template_name'],
                'title' => $slideData['title'],
                'display_order' => $slideData['display_order'],
            ]);

            foreach ($slideData['content_blocks'] as $blockData) {
                $slide->contentBlocks()->create([
                    'block_type' => $blockData['block_type'],
                    'content_data' => $blockData['content_data'],
                    'display_order' => $blockData['display_order'],
                ]);
            }
        }
    }

    /**
     * Get data for Dynamic Outdoor Proposal Template.
     *
     * @return array
     */
    private function getDynamicOutdoorProposalTemplateData(): array
    {
        return [
            'title' => 'Dynamic Outdoor Proposal Template',
            'slides' => [
                [
                    'template_name' => 'IntroCover',
                    'title' => 'Introduction',
                    'display_order' => 1,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Your Partner in Digital Transformation', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'We are pleased to present this proposal for a custom web application designed to streamline the quoting and ordering process for Dynamic Outdoors. Our mission is to build an efficient, scalable, and connected digital future for your business.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'details_list',
                            'content_data' => ['items' => [
                                'Prepared for: Benjamin Castledine',
                                'Prepared by: Zeeshan Sabri',
                                'Proposal: OZEE-475',
                            ]],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeColumn',
                    'title' => 'The Challenge',
                    'display_order' => 2,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'The Challenge: A Disconnected Workflow', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-pencil-alt',
                                'title' => 'Manual Quoting',
                                'description' => 'The current quoting process for Roller Shutters and Slidetracks is a time-consuming and manual task.',
                            ],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-list-alt',
                                'title' => 'Inefficient Order Management',
                                'description' => 'Lacking a central system makes tracking orders difficult and hinders operational efficiency.',
                            ],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-dollar-sign',
                                'title' => 'Complex Pricing',
                                'description' => 'Managing group-based discounts can be complicated, leading to potential pricing inconsistencies.',
                            ],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'FourColumn',
                    'title' => 'Unified Solution',
                    'display_order' => 3,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Our Unified Solution', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'We will develop a comprehensive digital platform to enhance operational efficiency and streamline your entire sales process. The application will be built around four core pillars:'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-users', 'title' => 'User & Group Management'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-file-invoice-dollar', 'title' => 'Quote & Order Management'],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-cog', 'title' => 'Product & Pricing Management'],
                            'display_order' => 5,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-link', 'title' => 'Invoice & Future Integration'],
                            'display_order' => 6,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageRight',
                    'title' => 'User Management',
                    'display_order' => 4,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'User & Group Management', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Gain complete control over user access and pricing structures with an intuitive management system.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Admin can create new user accounts and send login credentials via email.',
                                'Secure login for users to create, manage, and convert quotes into orders.',
                                'Organize users into groups with predefined percentage-based or fixed-price discounts.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/user_management.png', 'alt' => 'User management interface'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageLeft',
                    'title' => 'Quote & Order',
                    'display_order' => 5,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Quote & Order Management', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Streamline your entire sales cycle from initial quote to confirmed order with a seamless, automated system.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Users can generate, save, and modify multiple quotes for future reference.',
                                'Effortlessly convert confirmed quotes into orders with a single click.',
                                'Receive automatic email notifications for new orders placed.',
                                'Admin can track order status and add internal comments for team communication.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/quote.png', 'alt' => 'Quote and order dashboard'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageRight',
                    'title' => 'Product & Pricing',
                    'display_order' => 6,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Product & Pricing Management', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Maintain an accurate and dynamic product catalog while ensuring pricing consistency across all client groups.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Full administrative control to create, update, or remove product options as your business evolves.',
                                'Group-based discounts are automatically applied during quote generation for accuracy.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/pricing.png', 'alt' => 'Pricing management interface'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageLeft',
                    'title' => 'Integration',
                    'display_order' => 7,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Scalability & Future Growth', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Built on a robust technology stack, your application is ready for today\'s needs and tomorrow\'s growth.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Initial development includes manual invoice generation with Xero.',
                                'Future enhancement possibilities include full Xero and Monday.com integration.',
                                'Powered by a modern tech stack (Laravel, Livewire, MySQL) for optimal performance and scalability.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/integrations.png', 'alt' => 'Integration diagram'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'FourStepProcess',
                    'title' => 'Admin Journey',
                    'display_order' => 8,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'A Day in the Life: The Admin Journey', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 1, 'title' => 'Manage Users', 'description' => 'Creates client accounts, assigns them to groups, and sends login credentials.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 2, 'title' => 'Manage Products', 'description' => 'Adds or updates product options and defines group-level discounts.'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 3, 'title' => 'Process Orders', 'description' => 'Receives email notifications for new orders and updates their status.'],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 4, 'title' => 'Generate Invoices', 'description' => 'Manually generates and shares invoices with clients using Xero.'],
                            'display_order' => 5,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeStepProcess',
                    'title' => 'Client Journey',
                    'display_order' => 9,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Effortless Experience: The Client Journey', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 1, 'title' => 'Login', 'description' => 'Receives credentials from the admin and logs into the application securely.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 2, 'title' => 'Generate Quotes', 'description' => 'Selects products, enters details, and saves multiple quotes for reference.'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 3, 'title' => 'Place Order', 'description' => 'Reviews saved quotes and converts the desired quote into a confirmed order.'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithChart',
                    'title' => 'Why Us',
                    'display_order' => 10,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Why We\'re Your Ideal Partner', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_list',
                            'content_data' => ['items' => [
                                ['title' => 'Commitment to Quality', 'description' => 'We offer unlimited revisions within the project scope to ensure your complete satisfaction.'],
                                ['title' => 'Innovative & Scalable Solutions', 'description' => 'Our robust and scalable technology stack ensures your solution is performant and future-proof.'],
                                ['title' => 'Reliable Support', 'description' => 'Enjoy 3 months of free support post-deployment, with ongoing maintenance options available.'],
                            ]],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'image_block',
                            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/graph.png', 'title' => 'Projected Efficiency Gains'],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ProjectDetails',
                    'title' => 'Project Details',
                    'display_order' => 11,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Project Details', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'pricing_table',
                            'content_data' => ['price' => 'AUD 7,500 (+GST)', 'title' => 'Pricing & Payment Schedule', 'payment_schedule' => [
                                '25% upon project confirmation',
                                '25% pre-deployment',
                                '50% one week after successful deployment',
                            ]],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'timeline_table',
                            'content_data' => ['title' => 'Project Timeline', 'timeline' => [
                                ['phase' => 'Requirement Gathering & Planning', 'duration' => '1-2 Weeks'],
                                ['phase' => 'Backend & Frontend Development', 'duration' => '6-8 Weeks'],
                                ['phase' => 'Quality Assurance & Bug Fixing', 'duration' => '2 Weeks'],
                                ['phase' => 'Deployment', 'duration' => '1 Week'],
                            ]],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'CallToAction',
                    'title' => 'Call to Action',
                    'display_order' => 12,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Let\'s Build Your Future', 'level' => 1],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'We are confident this web application will transform your operations. We look forward to your confirmation to move forward and begin this exciting project.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/work_together.png', 'alt' => 'Two hands shaking'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'slogan',
                            'content_data' => ['text' => 'Innovate. Optimize. Grow.'],
                            'display_order' => 4,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get data for SEO Audit Report Template (based on fannit.html structure).
     *
     * @return array
     */
    private function getSEOAuditReportTemplateData(): array
    {
        return [
            'title' => 'SEO Audit Report Template',
            'slides' => [
                [
                    'template_name' => 'IntroCover',
                    'title' => 'Executive Summary',
                    'display_order' => 1,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Executive Summary', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Website: https://www.fannit.com Audit Dates: August 29 - September 1, 2025'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'details_list',
                            'content_data' => ['items' => [
                                'Performance: Needs significant improvement, especially on mobile.',
                                'SEO & Social: On-page issues were identified and the social media strategy is a missed opportunity for engagement.',
                                'Design: The website has visual and content inconsistencies that impact the user experience.',
                                'Accessibility: The website is "non-compliant" with numerous serious issues.',
                            ]],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeColumn',
                    'title' => 'Website Performance: The Current State',
                    'display_order' => 2,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Website Performance: The Current State', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'The website\'s overall performance scores, according to the Google PageSpeed Insights Report, indicate a need for improvement. The desktop score is 69 (Needs Improvement) while the mobile score is 43 (Poor). This underperformance is particularly critical on mobile devices, hindering user experience and search engine ranking.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-exclamation-triangle',
                                'title' => 'Render-blocking resources',
                                'description' => '',
                            ],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-exclamation-triangle',
                                'title' => 'Unoptimized images',
                                'description' => '',
                            ],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-exclamation-triangle',
                                'title' => 'Caching issues',
                                'description' => '',
                            ],
                            'display_order' => 5,
                        ],
                    ],
                ],
                [
                    'template_name' => 'FourColumn',
                    'title' => 'Website Performance: Desktop Metrics',
                    'display_order' => 3,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Core Web Vitals', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-clock', 'title' => 'First Contentful Paint (FCP): 0.5s'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-clock', 'title' => 'Largest Contentful Paint (LCP): 2.4s'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-clock', 'title' => 'Total Blocking Time (TBT): 340ms'],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-clock', 'title' => 'Speed Index: 1.9s'],
                            'display_order' => 5,
                        ],
                        // Note: CLS added as an extra, but template is FourColumn, adjusted accordingly
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-clock', 'title' => 'Cumulative Layout Shift (CLS): 0.007'],
                            'display_order' => 6,
                        ],
                    ],
                ],
                [
                    'template_name' => 'FourColumn',
                    'title' => 'Website Performance: Mobile Metrics',
                    'display_order' => 4,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Core Web Vitals', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-clock', 'title' => 'First Contentful Paint (FCP): 2.1s'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-clock', 'title' => 'Largest Contentful Paint (LCP): 7.7s'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-clock', 'title' => 'Total Blocking Time (TBT): 1450ms'],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-clock', 'title' => 'Speed Index: 5.8s'],
                            'display_order' => 5,
                        ],
                        // CLS and page weight
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-clock', 'title' => 'Cumulative Layout Shift (CLS): 0'],
                            'display_order' => 6,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'The total page weight is approximately 7.4 MB, a significant factor in the poor performance.'],
                            'display_order' => 7,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeColumn',
                    'title' => 'Website Performance: Recommendations',
                    'display_order' => 5,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Website Performance: Recommendations', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-compress',
                                'title' => 'Minimize Code & Files',
                                'description' => 'Remove unused JavaScript & CSS, and minify/compress files for faster loading.',
                            ],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-image',
                                'title' => 'Optimize Images',
                                'description' => 'Convert images to modern formats like WebP/AVIF and implement lazy loading to reduce page weight.',
                            ],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-server',
                                'title' => 'Improve Server Response',
                                'description' => 'Enable a CDN and configure caching headers to reduce Time to First Byte (TTFB).',
                            ],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageRight',
                    'title' => 'SEO Audit: Overview & Key Metrics',
                    'display_order' => 6,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'SEO Audit: Overview & Key Metrics', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'The goal of the SEO audit is to increase traffic and conversions for the fannit.com website.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Authority Score: 26',
                                'Moz Domain Authority: 38',
                                'The site has 35.5k backlinks from 1.7k referring domains.',
                                'Organic Search Traffic: $636 (+35% increase)',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=SEO+Metrics', 'alt' => 'SEO metrics overview'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeColumn',
                    'title' => 'SEO Issues: On-Page & Off-Page',
                    'display_order' => 7,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'SEO Issues: On-Page & Off-Page', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-exclamation-circle',
                                'title' => 'Missing Meta Titles & Descriptions',
                                'description' => 'Several key pages like marketing-leadership and website-design/services are missing these critical elements.',
                            ],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-exclamation-circle',
                                'title' => 'Images Without ALT Text',
                                'description' => 'Numerous images on the careers and blog pages are inaccessible to screen readers and search engines.',
                            ],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-exclamation-circle',
                                'title' => 'Blog Content Issues',
                                'description' => 'Some blog posts lack targeted keywords and have incorrect heading structures.',
                            ],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-exclamation-circle',
                                'title' => 'Need for Backlink Building',
                                'description' => 'To improve the site\'s authority and ranking, a strategic effort to build quality backlinks is needed.',
                            ],
                            'display_order' => 5,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeColumn',
                    'title' => 'SEO Recommendations: Content & Linking',
                    'display_order' => 8,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'SEO Recommendations: Content & Linking', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-link',
                                'title' => 'Build High-Quality Backlinks',
                                'description' => 'Secure links from reputable, industry-relevant sites to improve domain authority.',
                            ],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-pencil-alt',
                                'title' => 'Create Expert Content',
                                'description' => 'Develop well-researched, high-value content that establishes thought leadership.',
                            ],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-sitemap',
                                'title' => 'Strengthen Internal Linking',
                                'description' => 'Improve the flow of "link juice" and user navigation by connecting relevant pages.',
                            ],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageLeft',
                    'title' => 'SEO Action Plan & Expected Results',
                    'display_order' => 9,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'SEO Action Plan & Expected Results', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'High Priority: Optimize for existing keywords and earn high-quality backlinks.',
                                'Medium Priority: Create new keyword-focused content.',
                                'Domain Authority: Improve over time by building quality backlinks and creating expert content.',
                                'Organic Traffic: Increase traffic and conversions for the website.',
                            ]],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Action+Plan', 'alt' => 'SEO action plan diagram'],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeColumn',
                    'title' => 'Design & Usability: Visuals & Branding',
                    'display_order' => 10,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Design & Usability: Visuals & Branding', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-palette',
                                'title' => 'Color Palette',
                                'description' => 'Use a fresh, bright, and brand-aligned color palette with vibrant accent colors.',
                            ],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-image',
                                'title' => 'Client Logos',
                                'description' => 'Reduce client logos on the homepage by 20–30% for better design balance.',
                            ],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-mouse-pointer',
                                'title' => 'Button Styles',
                                'description' => 'All pages should have the same button styles, defined by color, shape, and size.',
                            ],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeColumn',
                    'title' => 'Design & Usability: Content & Forms',
                    'display_order' => 11,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Design & Usability: Content & Forms', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-text-height',
                                'title' => 'Paragraph Text',
                                'description' => 'Summarize into brief, easy-to-understand forms.',
                            ],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-form',
                                'title' => 'Form Placement',
                                'description' => 'Placement of forms inside dropdowns affects usability and visibility.',
                            ],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-quote-left',
                                'title' => 'Testimonials',
                                'description' => 'Make a slider for testimonials, which should be responsive and accessible.',
                            ],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageRight',
                    'title' => 'Accessibility Audit: Non-Compliant',
                    'display_order' => 12,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Accessibility Audit: Non-Compliant', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'The Fannit website is not inclusive or accessible to people with disabilities. The scan found 53 issues, with a severity breakdown of 42 severe, 9 moderate, and 2 mild. This raises legal risks and negatively impacts a significant portion of the user base.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Accessibility+Issues', 'alt' => 'Accessibility audit results'],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeColumn',
                    'title' => 'Accessibility Issues: Navigation & Structure',
                    'display_order' => 13,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Accessibility Issues: Navigation & Structure', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-keyboard',
                                'title' => 'Missing Skip Links',
                                'description' => 'Missing hidden skip links for keyboard users to bypass navigation.',
                            ],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-heading',
                                'title' => 'Heading Structure',
                                'description' => '22 instances where elements that visually function as headings are not coded with a heading tag.',
                            ],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-bars',
                                'title' => 'Menu Attributes',
                                'description' => 'Menu items with dropdowns are missing aria-haspopup and aria-expanded attributes.',
                            ],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'FourColumn',
                    'title' => 'Accessibility Issues: Elements & Readability',
                    'display_order' => 14,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Accessibility Issues: Elements & Readability', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-mouse-pointer', 'title' => 'Non-button tags used for buttons should include role="button".'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-external-link-alt', 'title' => 'Links that open in a new tab are not announced to screen readers.'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-eye', 'title' => 'Seven instances of low text contrast were found.'],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-image', 'title' => 'Some images are missing alt attributes.'],
                            'display_order' => 5,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageLeft',
                    'title' => 'Social Media Audit: The Opportunity',
                    'display_order' => 15,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Social Media Audit: The Opportunity', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'As a marketing agency, Fannit\'s social media should showcase its expertise, but the current approach on Instagram and Facebook fails to do so consistently. The strategy of simply sharing blog links doesn\'t effectively "run a business on social media".'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Shift to a visual-first, results-driven content strategy that uses social proof and direct engagement.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Social+Media', 'alt' => 'Social media opportunity'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeColumn',
                    'title' => 'Social Media Strategy: Instagram & Facebook',
                    'display_order' => 16,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Social Media Strategy: Instagram & Facebook', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-instagram',
                                'title' => 'Instagram Feed',
                                'description' => 'The feed should be a portfolio of Fannit’s success stories.',
                            ],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-instagram',
                                'title' => 'Instagram Content',
                                'description' => 'Transform blog content into native Instagram content like Reels or carousels.',
                            ],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-instagram',
                                'title' => 'Reels and Stories',
                                'description' => 'Use Reels and Stories for quick marketing tips.',
                            ],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-facebook',
                                'title' => 'Facebook Posts',
                                'description' => 'Create detailed posts that tell a client\'s story from problem to solution.',
                            ],
                            'display_order' => 5,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-facebook',
                                'title' => 'Facebook Engagement',
                                'description' => 'Actively engage with followers and consider creating a Facebook Group.',
                            ],
                            'display_order' => 6,
                        ],
                    ],
                ],
                [
                    'template_name' => 'FourStepProcess',
                    'title' => 'Final Prioritized Action Plan',
                    'display_order' => 17,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Final Prioritized Action Plan', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 1, 'title' => 'High Priority', 'description' => 'Fix all severe accessibility issues, optimize LCP, and fix on-page SEO issues.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 2, 'title' => 'Medium Priority', 'description' => 'Optimize images, implement caching & CDN, build backlinks, create content, launch social strategy.'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 3, 'title' => 'Low Priority', 'description' => 'Address minor design issues like footer size and internal linking.'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithChart',
                    'title' => 'Expected Results: A Transformed Digital Presence',
                    'display_order' => 18,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Expected Results: A Transformed Digital Presence', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_list',
                            'content_data' => ['items' => [
                                ['title' => 'Website Performance', 'description' => 'Scores will improve from 69 to 90+ on desktop and from 43 to 70–80 on mobile.'],
                                ['title' => 'SEO & Traffic', 'description' => 'Improved domain authority and increased organic traffic.'],
                                ['title' => 'User Experience', 'description' => 'Professional, easy-to-use, and fully accessible.'],
                                ['title' => 'Social Media', 'description' => 'Become a powerful marketing tool demonstrating expertise.'],
                            ]],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'image_block',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Expected+Results', 'title' => 'Projected Improvements'],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'CallToAction',
                    'title' => 'Call to Action',
                    'display_order' => 19,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Let\'s Transform Your Digital Presence', 'level' => 1],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'We are confident these changes will elevate your online presence. Contact us to get started.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Call+To+Action', 'alt' => 'Get Started'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'slogan',
                            'content_data' => ['text' => 'Optimize. Engage. Succeed.'],
                            'display_order' => 4,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get data for Marketing Campaign Proposal Template.
     *
     * @return array
     */
    private function getMarketingCampaignProposalTemplateData(): array
    {
        return [
            'title' => 'Marketing Campaign Proposal Template',
            'slides' => [
                [
                    'template_name' => 'IntroCover',
                    'title' => 'Introduction',
                    'display_order' => 1,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Your Partner in Marketing Excellence', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'We are excited to propose a comprehensive marketing campaign tailored to boost your brand visibility and drive conversions. Our strategy focuses on digital channels for maximum impact.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'details_list',
                            'content_data' => ['items' => [
                                'Prepared for: Client Name',
                                'Prepared by: Marketing Team',
                                'Proposal ID: MKT-123',
                            ]],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeColumn',
                    'title' => 'The Challenge',
                    'display_order' => 2,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'The Challenge: Market Saturation', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-chart-line',
                                'title' => 'Low Visibility',
                                'description' => 'Struggling to stand out in a crowded market.',
                            ],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-users',
                                'title' => 'Audience Engagement',
                                'description' => 'Difficulty in engaging target audience effectively.',
                            ],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-dollar-sign',
                                'title' => 'Conversion Rates',
                                'description' => 'Suboptimal conversion from leads to sales.',
                            ],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'FourColumn',
                    'title' => 'Our Solution',
                    'display_order' => 3,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Our Integrated Marketing Solution', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'We propose a multi-channel campaign built on four key pillars:'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-search', 'title' => 'SEO & Content Marketing'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-ad', 'title' => 'Paid Advertising'],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-share-alt', 'title' => 'Social Media Management'],
                            'display_order' => 5,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-envelope', 'title' => 'Email Campaigns'],
                            'display_order' => 6,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageRight',
                    'title' => 'SEO & Content',
                    'display_order' => 4,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'SEO & Content Marketing', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Enhance organic reach with optimized content.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Keyword research and on-page optimization.',
                                'High-quality blog posts and resources.',
                                'Backlink building strategy.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=SEO+Content', 'alt' => 'SEO and content interface'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageLeft',
                    'title' => 'Paid Advertising',
                    'display_order' => 5,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Paid Advertising', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Targeted ads to drive immediate traffic.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Google Ads and social media ads.',
                                'A/B testing for ad creatives.',
                                'Retargeting campaigns.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Paid+Ads', 'alt' => 'Paid advertising dashboard'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageRight',
                    'title' => 'Social Media Management',
                    'display_order' => 6,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Social Media Management', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Build community and engagement.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Content calendar and posting schedule.',
                                'Engagement with followers.',
                                'Influencer partnerships.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Social+Media', 'alt' => 'Social media management'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageLeft',
                    'title' => 'Email Campaigns',
                    'display_order' => 7,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Email Campaigns', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Nurture leads with personalized emails.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Segmented email lists.',
                                'Automated drip campaigns.',
                                'Performance tracking.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Email+Campaigns', 'alt' => 'Email campaign interface'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'FourStepProcess',
                    'title' => 'Campaign Journey',
                    'display_order' => 8,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'The Campaign Journey', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 1, 'title' => 'Planning', 'description' => 'Research and strategy development.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 2, 'title' => 'Execution', 'description' => 'Launch channels and content.'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 3, 'title' => 'Monitoring', 'description' => 'Track performance and adjust.'],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 4, 'title' => 'Reporting', 'description' => 'Analyze results and optimize.'],
                            'display_order' => 5,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithChart',
                    'title' => 'Why Us',
                    'display_order' => 9,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Why Choose Us', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_list',
                            'content_data' => ['items' => [
                                ['title' => 'Proven Track Record', 'description' => 'Successful campaigns with measurable ROI.'],
                                ['title' => 'Data-Driven Approach', 'description' => 'Decisions based on analytics and insights.'],
                                ['title' => 'Dedicated Support', 'description' => 'Ongoing assistance and reporting.'],
                            ]],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'image_block',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=ROI+Graph', 'title' => 'Projected ROI'],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ProjectDetails',
                    'title' => 'Project Details',
                    'display_order' => 10,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Project Details', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'pricing_table',
                            'content_data' => ['price' => 'AUD 5,000 (+GST)', 'title' => 'Pricing & Payment Schedule', 'payment_schedule' => [
                                '30% upfront',
                                '40% midway',
                                '30% upon completion',
                            ]],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'timeline_table',
                            'content_data' => ['title' => 'Campaign Timeline', 'timeline' => [
                                ['phase' => 'Planning', 'duration' => '2 Weeks'],
                                ['phase' => 'Execution', 'duration' => '4 Weeks'],
                                ['phase' => 'Optimization', 'duration' => '2 Weeks'],
                                ['phase' => 'Reporting', 'duration' => '1 Week'],
                            ]],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'CallToAction',
                    'title' => 'Call to Action',
                    'display_order' => 11,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Let\'s Launch Your Campaign', 'level' => 1],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'We look forward to partnering with you for marketing success.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Launch', 'alt' => 'Launch campaign'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'slogan',
                            'content_data' => ['text' => 'Engage. Convert. Grow.'],
                            'display_order' => 4,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get data for E-commerce Website Development Proposal Template.
     *
     * @return array
     */
    private function getEcommerceDevelopmentProposalTemplateData(): array
    {
        return [
            'title' => 'E-commerce Website Development Proposal Template',
            'slides' => [
                [
                    'template_name' => 'IntroCover',
                    'title' => 'Introduction',
                    'display_order' => 1,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Your E-commerce Solution Partner', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'This proposal outlines the development of a robust e-commerce platform to expand your online sales.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'details_list',
                            'content_data' => ['items' => [
                                'Prepared for: Client Name',
                                'Prepared by: Development Team',
                                'Proposal ID: EC-456',
                            ]],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeColumn',
                    'title' => 'The Challenge',
                    'display_order' => 2,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'The Challenge: Online Sales Barriers', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-shopping-cart',
                                'title' => 'Outdated Platform',
                                'description' => 'Current site lacks modern features.',
                            ],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-mobile-alt',
                                'title' => 'Mobile Responsiveness',
                                'description' => 'Poor experience on mobile devices.',
                            ],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => [
                                'icon' => 'fa-lock',
                                'title' => 'Security Concerns',
                                'description' => 'Need for better payment security.',
                            ],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'FourColumn',
                    'title' => 'Our Solution',
                    'display_order' => 3,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Comprehensive E-commerce Platform', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Built on four core components:'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-store', 'title' => 'Product Catalog'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-shopping-basket', 'title' => 'Shopping Cart & Checkout'],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-user-cog', 'title' => 'Admin Dashboard'],
                            'display_order' => 5,
                        ],
                        [
                            'block_type' => 'feature_card',
                            'content_data' => ['icon' => 'fa-plug', 'title' => 'Integrations'],
                            'display_order' => 6,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageRight',
                    'title' => 'Product Catalog',
                    'display_order' => 4,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Product Catalog Management', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Easy management of products and categories.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Unlimited products and variants.',
                                'Advanced search and filters.',
                                'SEO-friendly URLs.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Product+Catalog', 'alt' => 'Product catalog interface'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageLeft',
                    'title' => 'Shopping Cart & Checkout',
                    'display_order' => 5,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Shopping Cart & Checkout', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Seamless purchasing experience.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'One-page checkout.',
                                'Multiple payment gateways.',
                                'Abandoned cart recovery.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Checkout', 'alt' => 'Checkout process'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageRight',
                    'title' => 'Admin Dashboard',
                    'display_order' => 6,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Admin Dashboard', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Full control over the store.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Order management.',
                                'Inventory tracking.',
                                'Analytics and reports.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Admin+Dashboard', 'alt' => 'Admin dashboard'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithImageLeft',
                    'title' => 'Integrations',
                    'display_order' => 7,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Integrations & Scalability', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'Connect with essential tools.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'list_with_icons',
                            'content_data' => ['items' => [
                                'Payment processors like Stripe.',
                                'Shipping APIs.',
                                'CRM and email marketing tools.',
                            ]],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Integrations', 'alt' => 'Integrations diagram'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'FourStepProcess',
                    'title' => 'Development Journey',
                    'display_order' => 8,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'The Development Journey', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 1, 'title' => 'Requirements', 'description' => 'Gather and plan features.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 2, 'title' => 'Design', 'description' => 'UI/UX design.'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 3, 'title' => 'Development', 'description' => 'Build and integrate.'],
                            'display_order' => 4,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 4, 'title' => 'Testing & Launch', 'description' => 'QA and deployment.'],
                            'display_order' => 5,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ThreeStepProcess',
                    'title' => 'User Journey',
                    'display_order' => 9,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'User Journey', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 1, 'title' => 'Browse', 'description' => 'Explore products.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 2, 'title' => 'Add to Cart', 'description' => 'Select items.'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'step_card',
                            'content_data' => ['step_number' => 3, 'title' => 'Purchase', 'description' => 'Complete checkout.'],
                            'display_order' => 4,
                        ],
                    ],
                ],
                [
                    'template_name' => 'TwoColumnWithChart',
                    'title' => 'Why Us',
                    'display_order' => 10,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Why We\'re the Right Choice', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'feature_list',
                            'content_data' => ['items' => [
                                ['title' => 'Expertise in E-commerce', 'description' => 'Years of building successful online stores.'],
                                ['title' => 'Custom Solutions', 'description' => 'Tailored to your business needs.'],
                                ['title' => 'Post-Launch Support', 'description' => '3 months free maintenance.'],
                            ]],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'image_block',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Sales+Growth', 'title' => 'Projected Sales Growth'],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'ProjectDetails',
                    'title' => 'Project Details',
                    'display_order' => 11,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Project Details', 'level' => 2],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'pricing_table',
                            'content_data' => ['price' => 'AUD 10,000 (+GST)', 'title' => 'Pricing & Payment Schedule', 'payment_schedule' => [
                                '20% upon confirmation',
                                '30% after design',
                                '50% after launch',
                            ]],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'timeline_table',
                            'content_data' => ['title' => 'Project Timeline', 'timeline' => [
                                ['phase' => 'Planning & Design', 'duration' => '3-4 Weeks'],
                                ['phase' => 'Development', 'duration' => '8-10 Weeks'],
                                ['phase' => 'Testing', 'duration' => '2 Weeks'],
                                ['phase' => 'Deployment', 'duration' => '1 Week'],
                            ]],
                            'display_order' => 3,
                        ],
                    ],
                ],
                [
                    'template_name' => 'CallToAction',
                    'title' => 'Call to Action',
                    'display_order' => 12,
                    'content_blocks' => [
                        [
                            'block_type' => 'heading',
                            'content_data' => ['text' => 'Let\'s Build Your Online Store', 'level' => 1],
                            'display_order' => 1,
                        ],
                        [
                            'block_type' => 'paragraph',
                            'content_data' => ['text' => 'We are ready to transform your e-commerce vision into reality.'],
                            'display_order' => 2,
                        ],
                        [
                            'block_type' => 'image',
                            'content_data' => ['url' => 'https://placeholder.com/600x400?text=Online+Store', 'alt' => 'E-commerce success'],
                            'display_order' => 3,
                        ],
                        [
                            'block_type' => 'slogan',
                            'content_data' => ['text' => 'Sell. Scale. Succeed.'],
                            'display_order' => 4,
                        ],
                    ],
                ],
            ],
        ];
    }
}
