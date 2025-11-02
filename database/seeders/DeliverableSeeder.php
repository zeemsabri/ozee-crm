<?php

namespace Database\Seeders;

use App\Models\Deliverable;
use App\Models\Project;
use App\Models\User; // Assuming 'User' is your team member model
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class DeliverableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Ensure there's at least one project and one user
        $project = Project::first() ?? Project::factory()->create();
        $teamMember = User::first() ?? User::factory()->create();

        // Clear existing deliverables to avoid duplicates on re-seed
        // Deliverable::truncate(); // Use with caution in production if you don't want to lose data

        $deliverablesData = [];

        // Deliverable 1: Blog Post (Pending Review)
        $deliverablesData[] = [
            'project_id' => $project->id,
            'team_member_id' => $teamMember->id,
            'title' => 'Draft Blog Post: Top 5 Digital Marketing Trends',
            'description' => 'First draft of the blog post focusing on current digital marketing trends. Please review for content accuracy and tone.',
            'type' => 'blog_post',
            'status' => 'pending_review',
            'content_url' => 'https://docs.google.com/document/d/1sampleDocId_Blog1/edit',
            'content_text' => null,
            'attachment_path' => null,
            'version' => 1,
            'parent_deliverable_id' => null,
            'submitted_at' => now()->subDays(2),
            'overall_approved_at' => null,
            'overall_approved_by_client_id' => null,
            'due_for_review_by' => now()->addDays(5),
            'is_visible_to_client' => true,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ];

        // Deliverable 2: Social Media Post (Pending Review)
        $deliverablesData[] = [
            'project_id' => $project->id,
            'team_member_id' => $teamMember->id,
            'title' => 'Instagram Carousel Design: Product Launch',
            'description' => 'Carousel design for the new product launch on Instagram. Includes 3 slides and proposed caption text.',
            'type' => 'social_media_post',
            'status' => 'pending_review',
            'content_url' => 'https://placehold.co/600x400/FF00FF/000000?text=Social+Media+Mockup', // Placeholder image URL
            'content_text' => 'Caption: 🎉 Exciting news! Our new product is launching soon! Get ready for [Product Benefit]. #NewProduct #Innovation',
            'attachment_path' => null,
            'version' => 1,
            'parent_deliverable_id' => null,
            'submitted_at' => now()->subDay(),
            'overall_approved_at' => null,
            'overall_approved_by_client_id' => null,
            'due_for_review_by' => now()->addDays(3),
            'is_visible_to_client' => true,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ];

        // Deliverable 3: SEO Keywords (Approved)
        $deliverablesData[] = [
            'project_id' => $project->id,
            'team_member_id' => $teamMember->id,
            'title' => 'Q3 SEO Keyword Research & Strategy',
            'description' => 'Comprehensive list of target keywords for Q3. Focus on long-tail and high-intent terms.',
            'type' => 'seo_keywords',
            'status' => 'approved',
            'content_url' => null,
            'content_text' => json_encode([
                'main_keywords' => ['digital marketing agency', 'seo services perth', 'content marketing strategies'],
                'long_tail' => ['best digital marketing agency perth', 'how to improve google rankings quickly'],
            ]),
            'attachment_path' => null,
            'version' => 1,
            'parent_deliverable_id' => null,
            'submitted_at' => now()->subWeeks(2),
            'overall_approved_at' => now()->subWeeks(1),
            'overall_approved_by_client_id' => null, // Will be set by interaction seeder if client exists
            'due_for_review_by' => null,
            'is_visible_to_client' => true,
            'created_at' => now()->subWeeks(2),
            'updated_at' => now()->subWeeks(1),
        ];

        // Deliverable 4: Report (Revisions Requested)
        $deliverablesData[] = [
            'project_id' => $project->id,
            'team_member_id' => $teamMember->id,
            'title' => 'Monthly Performance Report - June',
            'description' => 'Detailed report on campaign performance for June. Client requested revisions on data interpretation.',
            'type' => 'report',
            'status' => 'revisions_requested',
            'content_url' => 'https://example.com/reports/june_report_v1.pdf',
            'content_text' => null,
            'attachment_path' => null,
            'version' => 1,
            'parent_deliverable_id' => null,
            'submitted_at' => now()->subWeeks(3),
            'overall_approved_at' => null,
            'overall_approved_by_client_id' => null,
            'due_for_review_by' => now()->subWeeks(1),
            'is_visible_to_client' => true,
            'created_at' => now()->subWeeks(3),
            'updated_at' => now()->subWeeks(1)->addDays(2),
        ];

        // Deliverable 5: Blog Post - Version 2 (Pending Review, revision of #1)
        $deliverablesData[] = [
            'project_id' => $project->id,
            'team_member_id' => $teamMember->id,
            'title' => 'Revised Blog Post: Top 5 Digital Marketing Trends (V2)',
            'description' => 'Second draft incorporating client feedback on tone and example content from previous submission.',
            'type' => 'blog_post',
            'status' => 'pending_review',
            'content_url' => 'https://docs.google.com/document/d/1sampleDocId_Blog2/edit',
            'content_text' => null,
            'attachment_path' => null,
            'version' => 2,
            'parent_deliverable_id' => 1, // Assuming Deliverable 1 has ID 1. Adjust if IDs are not sequential.
            'submitted_at' => now()->subHours(12),
            'overall_approved_at' => null,
            'overall_approved_by_client_id' => null,
            'due_for_review_by' => now()->addDays(7),
            'is_visible_to_client' => true,
            'created_at' => now()->subHours(12),
            'updated_at' => now()->subHours(12),
        ];

        foreach ($deliverablesData as $data) {
            Deliverable::create($data);
        }

        // After creating deliverables, find Deliverable 5 and link its parent if ID is not 1
        $deliverable5 = Deliverable::where('title', 'Revised Blog Post: Top 5 Digital Marketing Trends (V2)')->first();
        $deliverable1 = Deliverable::where('title', 'Draft Blog Post: Top 5 Digital Marketing Trends')->first();

        if ($deliverable5 && $deliverable1) {
            $deliverable5->parent_deliverable_id = $deliverable1->id;
            $deliverable5->save();
        }

        $this->command->info('Deliverables seeded successfully!');
    }
}
