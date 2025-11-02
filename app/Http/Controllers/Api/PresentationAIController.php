<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Presentation;
use App\Services\PresentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PresentationAIController extends Controller
{
    public function __construct(public PresentationService $presentationService) {}

    /**
     * Receives a prompt from the user, sends it to the Google Gemini API,
     * and returns a structured JSON response for a new slide.
     */
    public function generateSlide(Request $request, Presentation $presentation)
    {

        try {
            // 1. Validate the incoming request from our Vue front-end.
            $request->validate([
                'prompt' => 'required|string|max:1000',
                'template_name' => 'nullable|string',
            ]);

            $userPrompt = $request->input('prompt');
            $templateName = $request->input('template_name') ?? null;
            $apiKey = config('services.gemini.key');

            // 2. Ensure the API key is configured before proceeding.
            if (! $apiKey) {
                return response()->json(['error' => 'AI service is not configured on the server.'], 500);
            }

            // 3. Define the "System Prompt". This is our instruction to the AI.
            // It tells the AI to act as a presentation assistant and forces it
            // to return data in a specific JSON format that matches our database structure.
            $systemPrompt = file_get_contents('presentationSlidePrompt.txt');

            if ($templateName) {
                $userPrompt = "User-defined template: \"{$templateName}\"\n\nPrompt: {$userPrompt}";
            }

            // 4. Construct the payload for the Gemini API.
            $payload = [
                'contents' => [
                    ['parts' => [['text' => $userPrompt]]],
                ],
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ],
            ];

            // 5. Make the API call to Google using Laravel's HTTP Client.
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key={$apiKey}", $payload);

            // 6. Handle potential errors from the API call.
            if ($response->failed()) {
                return response()->json(['error' => 'Failed to communicate with the AI service. The service may be busy.'], 502);
            }

            // 7. Extract, parse, and return the structured content to our front-end.
            // The Gemini API returns a JSON object where the content we want is a JSON *string*.
            // So we extract that string first, and then decode it into a real JSON object.
            $generatedJsonString = $response->json('candidates.0.content.parts.0.text', '');

            if (empty($generatedJsonString)) {
                return response()->json(['error' => 'The AI returned an empty response. Try rephrasing your prompt.'], 422);
            }

            $minifiedResponse = json_decode($generatedJsonString, true);

            $this->createSlideFromData($presentation, $this->presentationService->translate($minifiedResponse));

            return response()->json(json_decode($generatedJsonString));

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * **NEW "IMPORTER" METHOD**
     * Creates a new slide and its content blocks from AI-generated JSON data.
     */
    public function createSlideFromAI(Request $request, Presentation $presentation): JsonResponse
    {
        // 1. Validate the JSON structure we received from our own front-end.
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'content_blocks' => 'required|array',
            'content_blocks.*.block_type' => ['required', 'string', Rule::in(['heading', 'paragraph', 'list_with_icons'])],
            'content_blocks.*.content_data' => 'required|array',
        ]);

        return $this->createSlideFromData($presentation, $validatedData);

    }

    public function createSlideFromData(Presentation $presentation, array $validatedData)
    {

        Log::info(json_encode($validatedData));
        try {
            // 2. Wrap the entire creation process in a database transaction.
            // This ensures that if any part fails, everything is rolled back.
            $newSlide = DB::transaction(function () use ($presentation, $validatedData) {

                // 3. Determine the display order for the new slide.
                // We'll place it at the end of the existing slides.
                $lastSlideOrder = $presentation->slides()->max('display_order') ?? 0;

                // 4. Create the main Slide record.
                $slide = $presentation->slides()->create([
                    'title' => $validatedData['title'],
                    'template_name' => $validatedData['template_name'], // Or determine a template name dynamically if needed
                    'display_order' => $lastSlideOrder + 1,
                ]);

                // 5. Loop through the content blocks and create them, just like the seeder.
                foreach ($validatedData['content_blocks'] as $index => $blockData) {
                    $slide->contentBlocks()->create([
                        'block_type' => $blockData['block_type'],
                        'content_data' => $blockData['content_data'],
                        'display_order' => $index + 1,
                    ]);
                }

                // Eager load the content blocks to return the full object
                return $slide->load('contentBlocks');
            });

            // 6. Return the newly created slide with a 201 "Created" status.
            return response()->json($newSlide, 201);

        } catch (\Throwable $e) {
            // If anything inside the transaction fails, catch the error.
            report($e); // Log the actual error for debugging

            return response()->json(['error' => 'Failed to save the new slide to the database.'], 500);
        }
    }
}
