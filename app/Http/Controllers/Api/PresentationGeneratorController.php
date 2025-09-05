<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Lead;
use App\Models\Presentation;

class PresentationGeneratorController extends Controller
{
    /**
     * Generate a new presentation with a random, structured set of slides.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'slide_count' => 'required|integer|min:3|max:20',
        ]);

        // In a real application, you would use the authenticated user's client/lead.
        // For now, we'll associate it with the first available Lead.
        $lead = Lead::first();
        if (!$lead) {
            return response()->json(['message' => 'No available lead to associate the presentation with.'], 404);
        }

        // The presentation_templates.php file contains functions, so we require it once.
        require_once config_path('presentation_templates.php');

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

                // 2. Add the mandatory Intro Cover slide.
                $this->createSlideFromStructure($presentation, getStandardIntroCover());

                // 3. Generate the middle slides.
                $middleSlideCount = $slideCount - 2;
                $this->generateMiddleSlides($presentation, $middleSlideCount);

                // 4. Add the mandatory Call to Action slide.
                $this->createSlideFromStructure($presentation, getStandardCtaSlide($slideCount));
            });
        } catch (\Exception $e) {
            // If anything goes wrong, return a server error.
            return response()->json(['message' => 'Failed to generate presentation.', 'error' => $e->getMessage()], 500);
        }

        // Return the newly created presentation with its slides.
        return response()->json($presentation->load('slides.contentBlocks'), 201);
    }

    /**
     * Generate a random selection of middle slides for the presentation.
     *
     * @param Presentation $presentation
     * @param int $count
     * @return void
     */
    private function generateMiddleSlides(Presentation $presentation, int $count): void
    {
        // A pool of available slide-generating functions from our config library.
        // This defines the "story arc" of a typical presentation.
        $slideFunctionPool = [
            'getChallengeSlide',
            'getSolutionSlide',
            'getServiceDetailSlide',
            'getProcessSlide',
            'getWhyUsSlide',
            'getProjectDetailsSlide',
        ];

        for ($i = 0; $i < $count; $i++) {
            // Pick a random slide function from the pool.
            $randomFunction = $slideFunctionPool[array_rand($slideFunctionPool)];

            // Get the structure for that slide using generic, placeholder data.
            $slideStructure = $this->getSlideDataForFunction($randomFunction, $i + 2);

            if ($slideStructure) {
                $this->createSlideFromStructure($presentation, $slideStructure);
            }
        }
    }

    /**
     * A helper to provide generic data to our slide library functions.
     *
     * @param string $functionName
     * @param int $order
     * @return array|null
     */
    private function getSlideDataForFunction(string $functionName, int $order): ?array
    {
        switch ($functionName) {
            case 'getChallengeSlide':
                return getChallengeSlide($order, 'Key Challenges We\'ve Identified', [
                    ['icon' => 'fa-puzzle-piece', 'title' => 'Challenge One', 'description' => 'A brief description of the first challenge.'],
                    ['icon' => 'fa-puzzle-piece', 'title' => 'Challenge Two', 'description' => 'A brief description of the second challenge.'],
                    ['icon' => 'fa-puzzle-piece', 'title' => 'Challenge Three', 'description' => 'A brief description of the third challenge.'],
                ]);
            case 'getSolutionSlide':
                return getSolutionSlide($order, 'A Tailored Solution', 'Our strategy is built on four key pillars to address your needs.', [
                    ['icon' => 'fa-lightbulb', 'title' => 'Pillar One'],
                    ['icon' => 'fa-lightbulb', 'title' => 'Pillar Two'],
                    ['icon' => 'fa-lightbulb', 'title' => 'Pillar Three'],
                    ['icon' => 'fa-lightbulb', 'title' => 'Pillar Four'],
                ]);
            case 'getServiceDetailSlide':
                return getServiceDetailSlide($order, 'Core Service Detail', 'TwoColumnWithImageRight', [
                    'heading' => 'A Core Service Offering', 'paragraph' => 'A detailed explanation of a key service we provide.',
                    'items' => ['Key feature or benefit one.', 'Key feature or benefit two.', 'Key feature or benefit three.'],
                    'image_url' => 'https://placehold.co/600x400/29438E/FFFFFF?text=Service+Detail', 'image_alt' => 'Service Detail Image',
                ]);
            case 'getProcessSlide':
                return getProcessSlide($order, 'Our Strategic Process', [
                    ['title' => 'Step 1: Discovery', 'description' => 'Understanding your unique goals and requirements.'],
                    ['title' => 'Step 2: Strategy', 'description' => 'Developing a data-driven plan for success.'],
                    ['title' => 'Step 3: Execution', 'description' => 'Implementing the strategy with precision and expertise.'],
                    ['title' => 'Step 4: Analysis', 'description' => 'Measuring results and optimizing for continuous improvement.'],
                ]);
            case 'getWhyUsSlide':
                return getWhyUsSlide($order);
            case 'getProjectDetailsSlide':
                return getProjectDetailsSlide($order,
                    ['price' => 'To Be Determined', 'title' => 'Investment', 'payment_schedule' => ['Payment terms to be discussed.']],
                    ['title' => 'Estimated Timeline', 'timeline' => [['phase' => 'Project Phase 1', 'duration' => 'X Weeks'], ['phase' => 'Project Phase 2', 'duration' => 'Y Weeks']]]
                );
            default:
                return null;
        }
    }

    /**
     * Creates a slide and its content blocks from a structured array.
     *
     * @param Presentation $presentation
     * @param array $structure
     * @return void
     */
    private function createSlideFromStructure(Presentation $presentation, array $structure): void
    {
        $slide = $presentation->slides()->create([
            'template_name' => $structure['template_name'],
            'title' => $structure['title'],
            'display_order' => $structure['display_order'],
        ]);

        if (!empty($structure['content_blocks'])) {
            foreach ($structure['content_blocks'] as $blockData) {
                $slide->contentBlocks()->create([
                    'block_type' => $blockData['block_type'],
                    'content_data' => $blockData['content_data'],
                    'display_order' => $blockData['display_order'],
                ]);
            }
        }
    }
}
