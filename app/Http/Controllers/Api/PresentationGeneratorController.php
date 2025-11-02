<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Presentation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PresentationGeneratorController extends Controller
{
    /**
     * Generate a new presentation with a random, structured set of slides.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'slide_count' => 'required|integer|min:3|max:20',
        ]);

        $lead = Lead::first();
        if (! $lead) {
            return response()->json(['message' => 'No available lead to associate the presentation with.'], 404);
        }

        $slideCount = $request->input('slide_count');
        $presentation = null;

        try {
            DB::transaction(function () use ($request, $lead, $slideCount, &$presentation) {
                // 1. Create the main presentation record.
                $presentation = $lead->presentations()->create([
                    'title' => $request->input('title'),
                    'type' => Presentation::PROPOSAL,
                    'is_template' => false,
                ]);

                // 2. Add the mandatory Intro Cover slide from the config blueprint.
                $introBlueprint = config('presentation_templates.slide_blueprints.intro_cover');
                $this->createSlideFromStructure($presentation, $introBlueprint, 1);

                // 3. Generate the middle slides.
                $middleSlideCount = $slideCount - 2;
                $this->generateMiddleSlides($presentation, $middleSlideCount);

                // 4. Add the mandatory Call to Action slide from the config blueprint.
                $ctaBlueprint = config('presentation_templates.slide_blueprints.call_to_action');
                $this->createSlideFromStructure($presentation, $ctaBlueprint, $slideCount);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate presentation.', 'error' => $e->getMessage()], 500);
        }

        return response()->json($presentation->load('slides.contentBlocks'), 201);
    }

    /**
     * Generate a random selection of middle slides for the presentation.
     */
    private function generateMiddleSlides(Presentation $presentation, int $count): void
    {
        // Fetch the pool of allowed slide blueprints from the config file.
        $slidePoolKeys = config('presentation_templates.generator_pool', []);
        $allBlueprints = config('presentation_templates.slide_blueprints');

        if (empty($slidePoolKeys)) {
            return; // No pool defined, so no slides to generate.
        }

        for ($i = 0; $i < $count; $i++) {
            // Pick a random slide key from the pool.
            $randomKey = $slidePoolKeys[array_rand($slidePoolKeys)];

            // Get the blueprint structure for that slide.
            $slideBlueprint = $allBlueprints[$randomKey] ?? null;

            if ($slideBlueprint) {
                // The display order is its position + 2 (since intro is 1).
                $this->createSlideFromStructure($presentation, $slideBlueprint, $i + 2);
            }
        }
    }

    /**
     * Creates a slide and its content blocks from a structured blueprint array.
     */
    private function createSlideFromStructure(Presentation $presentation, array $blueprint, int $displayOrder): void
    {
        $slide = $presentation->slides()->create([
            'template_name' => $blueprint['template_name'],
            'title' => $blueprint['title'],
            'display_order' => $displayOrder,
        ]);

        if (! empty($blueprint['content_blocks'])) {
            // Add a display_order to each block sequentially.
            collect($blueprint['content_blocks'])->each(function ($blockData, $index) use ($slide) {
                $slide->contentBlocks()->create([
                    'block_type' => $blockData['block_type'],
                    'content_data' => $blockData['content_data'],
                    'display_order' => $index + 1,
                ]);
            });
        }
    }
}
