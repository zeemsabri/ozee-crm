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
    public function run(): void
    {
        // Sample Client
        $client = Client::first() ?? Client::create([
            'name' => 'Acme Corp',
            'email' => 'contact@acme.example',
            'phone' => '1234567890',
        ]);

        // Client Presentation
        $clientPresentation = Presentation::create([
            'presentable_id' => $client->id,
            'presentable_type' => Client::class,
            'title' => 'Acme SEO Audit',
            'type' => 'audit_report',
        ]);

        $slide1 = $clientPresentation->slides()->create([
            'template_name' => 'Heading',
            'title' => 'Intro',
            'display_order' => 1,
        ]);
        $slide1->contentBlocks()->create([
            'block_type' => 'heading',
            'content_data' => [ 'text' => 'SEO Audit Overview', 'level' => 2 ],
            'display_order' => 1,
        ]);
        $slide1->contentBlocks()->create([
            'block_type' => 'paragraph',
            'content_data' => [ 'text' => 'We analyzed your site and found improvements.' ],
            'display_order' => 2,
        ]);

        // Sample Lead
        $lead = Lead::first() ?? Lead::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'company' => 'Future Co',
        ]);

        // Lead Presentation
        $leadPresentation = Presentation::create([
            'presentable_id' => $lead->id,
            'presentable_type' => Lead::class,
            'title' => 'Proposal for Future Co',
            'type' => 'proposal',
        ]);
        $slide2 = $leadPresentation->slides()->create([
            'template_name' => 'TwoColumnWithImage',
            'title' => 'Our Solution',
            'display_order' => 1,
        ]);
        $slide2->contentBlocks()->create([
            'block_type' => 'feature_card',
            'content_data' => [
                'icon' => 'fa-user',
                'title' => 'User Management',
                'description' => 'Manage users efficiently.',
            ],
            'display_order' => 1,
        ]);
    }
}
