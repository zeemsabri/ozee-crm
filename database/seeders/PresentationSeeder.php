<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lead;
use App\Models\Presentation;

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
        $lead = Lead::first() ?? Lead::firstOrCreate([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'company' => 'Future Co',
        ]);

        // Fetch all template data from the new config file.
        $templates = config('presentation_templates.templates', []);

        if (empty($templates)) {
            $this->command->warn('No presentation templates found in config/presentation_templates.php. Seeder will exit.');
            return;
        }

        foreach ($templates as $templateData) {
            $this->createTemplate($lead, $templateData);
        }

        $this->command->info('Template creation complete.');
    }

    /**
     * Create a presentation template with slides and content blocks from config data.
     *
     * @param Lead $lead
     * @param array $data
     * @return void
     */
    private function createTemplate(Lead $lead, array $data): void
    {
        $template = $lead->presentations()->updateOrCreate(
            ['title' => $data['title'], 'is_template' => true],
            ['type'  => Presentation::PROPOSAL]
        );

        // If the template is being updated, clear out old slides to prevent duplicates.
        if (!$template->wasRecentlyCreated) {
            $template->slides()->delete();
        }

        $this->command->info('Creating/Updating Template: ' . $data['title'] . '...');

        foreach ($data['slides'] as $slideData) {
            // Create the slide record.
            $slide = $template->slides()->create([
                'template_name' => $slideData['template_name'],
                'title' => $slideData['title'],
                'display_order' => $slideData['display_order'],
            ]);

            // Check if there are content blocks to create.
            if (!empty($slideData['content_blocks'])) {
                foreach ($slideData['content_blocks'] as $blockData) {
                    $slide->contentBlocks()->create([
                        'block_type' => $blockData['block_type'],
                        'content_data' => $blockData['content_data'],
                        'display_order' => $blockData['display_order'],
                    ]);
                }
            }
        }
    }
}
