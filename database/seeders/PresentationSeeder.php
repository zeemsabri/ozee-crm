<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lead;
use App\Models\Presentation;
use Illuminate\Support\Arr;

class PresentationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $lead = Lead::first() ?? Lead::firstOrCreate([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'company' => 'Future Co',
        ]);

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

        if (!$template->wasRecentlyCreated) {
            $template->slides()->delete();
        }

        $this->command->info('Creating/Updating Template: ' . $data['title'] . '...');

        $allBlueprints = config('presentation_templates.slide_blueprints');

        foreach ($data['slides'] as $index => $slideData) {
            $blueprintKey = $slideData['blueprint'];
            $baseSlide = $allBlueprints[$blueprintKey] ?? null;

            if (!$baseSlide) {
                $this->command->error("Blueprint '{$blueprintKey}' not found for template '{$data['title']}'. Skipping slide.");
                continue;
            }

            // Merge blueprint with overrides. `Arr::dot` and `Arr::set` can be used for deeper merges if needed.
            $finalSlideData = array_merge($baseSlide, $slideData['overrides'] ?? []);

            // Create the slide record.
            $slide = $template->slides()->create([
                'template_name' => $finalSlideData['template_name'],
                'title' => $finalSlideData['title'],
                'display_order' => $index + 1,
            ]);

            // Create content blocks with sequential display order.
            if (!empty($finalSlideData['content_blocks'])) {
                collect($finalSlideData['content_blocks'])->each(function ($blockData, $blockIndex) use ($slide) {
                    $slide->contentBlocks()->create([
                        'block_type' => $blockData['block_type'],
                        'content_data' => $blockData['content_data'],
                        'display_order' => $blockIndex + 1,
                    ]);
                });
            }
        }
    }
}
