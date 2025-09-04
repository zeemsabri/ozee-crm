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

        // ====================================================================
        // TEMPLATE SEEDER: Create a reusable proposal template from the HTML
        // ====================================================================

        // This will be our new template presentation
        $template = $lead->presentations()->updateOrCreate([
            'title' => 'Dynamic Outdoor Proposal Template',
            'type'  =>  Presentation::PROPOSAL,
            'is_template' => true,
        ]);
        // If the template already exists, delete its slides and content blocks
        if ($template->exists) {
            $template->slides()->delete();
        }
        $template->save();
        $this->command->info('Creating Dynamic Outdoor Proposal Template...');


        // Slide 1: Intro
        $slide1 = $template->slides()->create([
            'template_name' => 'IntroCover',
            'title' => 'Introduction',
            'display_order' => 1,
        ]);
        $slide1->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'Your Partner in Digital Transformation', 'level' => 2],
            'display_order' => 1,
        ]);
        $slide1->contentBlocks()->create([
            'block_type' => 'paragraph',
            'content_data' => ['text' => 'We are pleased to present this proposal for a custom web application designed to streamline the quoting and ordering process for Dynamic Outdoors. Our mission is to build an efficient, scalable, and connected digital future for your business.'],
            'display_order' => 2,
        ]);
        $slide1->contentBlocks()->create([
            'block_type' => 'details_list',
            'content_data' => ['items' => [
                'Prepared for: Benjamin Castledine',
                'Prepared by: Zeeshan Sabri',
                'Proposal: OZEE-475',
            ]],
            'display_order' => 3,
        ]);

        // Slide 2: The Challenge
        $slide2 = $template->slides()->create([
            'template_name' => 'ThreeColumn',
            'title' => 'The Challenge',
            'display_order' => 2,
        ]);
        $slide2->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'The Challenge: A Disconnected Workflow', 'level' => 2],
            'display_order' => 1,
        ]);
        $slide2->contentBlocks()->create([
            'block_type' => 'feature_card',
            'content_data' => [
                'icon' => 'fa-pencil-alt',
                'title' => 'Manual Quoting',
                'description' => 'The current quoting process for Roller Shutters and Slidetracks is a time-consuming and manual task.',
            ],
            'display_order' => 2,
        ]);
        $slide2->contentBlocks()->create([
            'block_type' => 'feature_card',
            'content_data' => [
                'icon' => 'fa-list-alt',
                'title' => 'Inefficient Order Management',
                'description' => 'Lacking a central system makes tracking orders difficult and hinders operational efficiency.',
            ],
            'display_order' => 3,
        ]);
        $slide2->contentBlocks()->create([
            'block_type' => 'feature_card',
            'content_data' => [
                'icon' => 'fa-dollar-sign',
                'title' => 'Complex Pricing',
                'description' => 'Managing group-based discounts can be complicated, leading to potential pricing inconsistencies.',
            ],
            'display_order' => 4,
        ]);

        // Slide 3: Solution Overview
        $slide3 = $template->slides()->create([
            'template_name' => 'FourColumn',
            'title' => 'Unified Solution',
            'display_order' => 3,
        ]);
        $slide3->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'Our Unified Solution', 'level' => 2],
            'display_order' => 1,
        ]);
        $slide3->contentBlocks()->create([
            'block_type' => 'paragraph',
            'content_data' => ['text' => 'We will develop a comprehensive digital platform to enhance operational efficiency and streamline your entire sales process. The application will be built around four core pillars:'],
            'display_order' => 2,
        ]);
        $slide3->contentBlocks()->create([
            'block_type' => 'feature_card',
            'content_data' => ['icon' => 'fa-users', 'title' => 'User & Group Management'],
            'display_order' => 3,
        ]);
        $slide3->contentBlocks()->create([
            'block_type' => 'feature_card',
            'content_data' => ['icon' => 'fa-file-invoice-dollar', 'title' => 'Quote & Order Management'],
            'display_order' => 4,
        ]);
        $slide3->contentBlocks()->create([
            'block_type' => 'feature_card',
            'content_data' => ['icon' => 'fa-cog', 'title' => 'Product & Pricing Management'],
            'display_order' => 5,
        ]);
        $slide3->contentBlocks()->create([
            'block_type' => 'feature_card',
            'content_data' => ['icon' => 'fa-link', 'title' => 'Invoice & Future Integration'],
            'display_order' => 6,
        ]);

        // Slide 4: User Management
        $slide4 = $template->slides()->create([
            'template_name' => 'TwoColumnWithImageRight',
            'title' => 'User Management',
            'display_order' => 4,
        ]);
        $slide4->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'User & Group Management', 'level' => 2],
            'display_order' => 1,
        ]);
        $slide4->contentBlocks()->create([
            'block_type' => 'paragraph',
            'content_data' => ['text' => 'Gain complete control over user access and pricing structures with an intuitive management system.'],
            'display_order' => 2,
        ]);
        $slide4->contentBlocks()->create([
            'block_type' => 'list_with_icons',
            'content_data' => ['items' => [
                'Admin can create new user accounts and send login credentials via email.',
                'Secure login for users to create, manage, and convert quotes into orders.',
                'Organize users into groups with predefined percentage-based or fixed-price discounts.',
            ]],
            'display_order' => 3,
        ]);
        $slide4->contentBlocks()->create([
            'block_type' => 'image',
            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/user_management.png', 'alt' => 'User management interface'],
            'display_order' => 4,
        ]);

        // Slide 5: Quote & Order
        $slide5 = $template->slides()->create([
            'template_name' => 'TwoColumnWithImageLeft',
            'title' => 'Quote & Order',
            'display_order' => 5,
        ]);
        $slide5->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'Quote & Order Management', 'level' => 2],
            'display_order' => 1,
        ]);
        $slide5->contentBlocks()->create([
            'block_type' => 'paragraph',
            'content_data' => ['text' => 'Streamline your entire sales cycle from initial quote to confirmed order with a seamless, automated system.'],
            'display_order' => 2,
        ]);
        $slide5->contentBlocks()->create([
            'block_type' => 'list_with_icons',
            'content_data' => ['items' => [
                'Users can generate, save, and modify multiple quotes for future reference.',
                'Effortlessly convert confirmed quotes into orders with a single click.',
                'Receive automatic email notifications for new orders placed.',
                'Admin can track order status and add internal comments for team communication.',
            ]],
            'display_order' => 3,
        ]);
        $slide5->contentBlocks()->create([
            'block_type' => 'image',
            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/quote.png', 'alt' => 'Quote and order dashboard'],
            'display_order' => 4,
        ]);

        // Slide 6: Product & Pricing
        $slide6 = $template->slides()->create([
            'template_name' => 'TwoColumnWithImageRight',
            'title' => 'Product & Pricing',
            'display_order' => 6,
        ]);
        $slide6->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'Product & Pricing Management', 'level' => 2],
            'display_order' => 1,
        ]);
        $slide6->contentBlocks()->create([
            'block_type' => 'paragraph',
            'content_data' => ['text' => 'Maintain an accurate and dynamic product catalog while ensuring pricing consistency across all client groups.'],
            'display_order' => 2,
        ]);
        $slide6->contentBlocks()->create([
            'block_type' => 'list_with_icons',
            'content_data' => ['items' => [
                'Full administrative control to create, update, or remove product options as your business evolves.',
                'Group-based discounts are automatically applied during quote generation for accuracy.',
            ]],
            'display_order' => 3,
        ]);
        $slide6->contentBlocks()->create([
            'block_type' => 'image',
            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/pricing.png', 'alt' => 'Pricing management interface'],
            'display_order' => 4,
        ]);

        // Slide 7: Integration
        $slide7 = $template->slides()->create([
            'template_name' => 'TwoColumnWithImageLeft',
            'title' => 'Integration',
            'display_order' => 7,
        ]);
        $slide7->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'Scalability & Future Growth', 'level' => 2],
            'display_order' => 1,
        ]);
        $slide7->contentBlocks()->create([
            'block_type' => 'paragraph',
            'content_data' => ['text' => 'Built on a robust technology stack, your application is ready for today\'s needs and tomorrow\'s growth.'],
            'display_order' => 2,
        ]);
        $slide7->contentBlocks()->create([
            'block_type' => 'list_with_icons',
            'content_data' => ['items' => [
                'Initial development includes manual invoice generation with Xero.',
                'Future enhancement possibilities include full Xero and Monday.com integration.',
                'Powered by a modern tech stack (Laravel, Livewire, MySQL) for optimal performance and scalability.',
            ]],
            'display_order' => 3,
        ]);
        $slide7->contentBlocks()->create([
            'block_type' => 'image',
            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/integrations.png', 'alt' => 'Integration diagram'],
            'display_order' => 4,
        ]);

        // Slide 8: Admin Journey
        $slide8 = $template->slides()->create([
            'template_name' => 'FourStepProcess',
            'title' => 'Admin Journey',
            'display_order' => 8,
        ]);
        $slide8->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'A Day in the Life: The Admin Journey', 'level' => 2],
            'display_order' => 1,
        ]);
        $slide8->contentBlocks()->create([
            'block_type' => 'step_card',
            'content_data' => ['step_number' => 1, 'title' => 'Manage Users', 'description' => 'Creates client accounts, assigns them to groups, and sends login credentials.'],
            'display_order' => 2,
        ]);
        $slide8->contentBlocks()->create([
            'block_type' => 'step_card',
            'content_data' => ['step_number' => 2, 'title' => 'Manage Products', 'description' => 'Adds or updates product options and defines group-level discounts.'],
            'display_order' => 3,
        ]);
        $slide8->contentBlocks()->create([
            'block_type' => 'step_card',
            'content_data' => ['step_number' => 3, 'title' => 'Process Orders', 'description' => 'Receives email notifications for new orders and updates their status.'],
            'display_order' => 4,
        ]);
        $slide8->contentBlocks()->create([
            'block_type' => 'step_card',
            'content_data' => ['step_number' => 4, 'title' => 'Generate Invoices', 'description' => 'Manually generates and shares invoices with clients using Xero.'],
            'display_order' => 5,
        ]);

        // Slide 9: Client Journey
        $slide9 = $template->slides()->create([
            'template_name' => 'ThreeStepProcess',
            'title' => 'Client Journey',
            'display_order' => 9,
        ]);
        $slide9->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'Effortless Experience: The Client Journey', 'level' => 2],
            'display_order' => 1,
        ]);
        $slide9->contentBlocks()->create([
            'block_type' => 'step_card',
            'content_data' => ['step_number' => 1, 'title' => 'Login', 'description' => 'Receives credentials from the admin and logs into the application securely.'],
            'display_order' => 2,
        ]);
        $slide9->contentBlocks()->create([
            'block_type' => 'step_card',
            'content_data' => ['step_number' => 2, 'title' => 'Generate Quotes', 'description' => 'Selects products, enters details, and saves multiple quotes for reference.'],
            'display_order' => 3,
        ]);
        $slide9->contentBlocks()->create([
            'block_type' => 'step_card',
            'content_data' => ['step_number' => 3, 'title' => 'Place Order', 'description' => 'Reviews saved quotes and converts the desired quote into a confirmed order.'],
            'display_order' => 4,
        ]);

        // Slide 10: Differentiator
        $slide10 = $template->slides()->create([
            'template_name' => 'TwoColumnWithChart',
            'title' => 'Why Us',
            'display_order' => 10,
        ]);
        $slide10->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'Why We\'re Your Ideal Partner', 'level' => 2],
            'display_order' => 1,
        ]);
        $slide10->contentBlocks()->create([
            'block_type' => 'feature_list',
            'content_data' => ['items' => [
                ['title' => 'Commitment to Quality', 'description' => 'We offer unlimited revisions within the project scope to ensure your complete satisfaction.'],
                ['title' => 'Innovative & Scalable Solutions', 'description' => 'Our robust and scalable technology stack ensures your solution is performant and future-proof.'],
                ['title' => 'Reliable Support', 'description' => 'Enjoy 3 months of free support post-deployment, with ongoing maintenance options available.'],
            ]],
            'display_order' => 2,
        ]);
        $slide10->contentBlocks()->create([
            'block_type' => 'image_block',
            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/graph.png', 'title' => 'Projected Efficiency Gains'],
            'display_order' => 3,
        ]);

        // Slide 11: Project Details
        $slide11 = $template->slides()->create([
            'template_name' => 'ProjectDetails',
            'title' => 'Project Details',
            'display_order' => 11,
        ]);
        $slide11->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'Project Details', 'level' => 2],
            'display_order' => 1,
        ]);
        $slide11->contentBlocks()->create([
            'block_type' => 'pricing_table',
            'content_data' => ['price' => 'AUD 7,500 (+GST)', 'title' => 'Pricing & Payment Schedule', 'payment_schedule' => [
                '25% upon project confirmation',
                '25% pre-deployment',
                '50% one week after successful deployment',
            ]],
            'display_order' => 2,
        ]);
        $slide11->contentBlocks()->create([
            'block_type' => 'timeline_table',
            'content_data' => ['title' => 'Project Timeline', 'timeline' => [
                ['phase' => 'Requirement Gathering & Planning', 'duration' => '1-2 Weeks'],
                ['phase' => 'Backend & Frontend Development', 'duration' => '6-8 Weeks'],
                ['phase' => 'Quality Assurance & Bug Fixing', 'duration' => '2 Weeks'],
                ['phase' => 'Deployment', 'duration' => '1 Week'],
            ]],
            'display_order' => 3,
        ]);

        // Slide 12: CTA
        $slide12 = $template->slides()->create([
            'template_name' => 'CallToAction',
            'title' => 'Call to Action',
            'display_order' => 12,
        ]);
        $slide12->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => ['text' => 'Let\'s Build Your Future', 'level' => 1],
            'display_order' => 1,
        ]);
        $slide12->contentBlocks()->create([
            'block_type' => 'paragraph',
            'content_data' => ['text' => 'We are confident this web application will transform your operations. We look forward to your confirmation to move forward and begin this exciting project.'],
            'display_order' => 2,
        ]);
        $slide12->contentBlocks()->create([
            'block_type' => 'image',
            'content_data' => ['url' => 'https://ozeeweb.com.au/wp-content/uploads/2025/09/work_together.png', 'alt' => 'Two hands shaking'],
            'display_order' => 3,
        ]);
        $slide12->contentBlocks()->create([
            'block_type' => 'slogan',
            'content_data' => ['text' => 'Innovate. Optimize. Grow.'],
            'display_order' => 4,
        ]);

        $this->command->info('Template creation complete.');
    }
}
