<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\PlaceholderDefinition; // <-- New Import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EmailTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     * Return JSON for API use
     */
    public function index()
    {
        return response()->json(EmailTemplate::with('placeholders')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:email_templates,slug',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $template = EmailTemplate::create($validatedData);

            // Extract placeholder names from the content using the corrected regex
            $placeholderNames = $this->extractPlaceholders($template->subject . ' ' . $template->body_html);
            $placeholderIds = PlaceholderDefinition::whereIn('name', $placeholderNames)->pluck('id');

            $template->placeholders()->sync($placeholderIds);

            DB::commit();

            return response()->json($template->load('placeholders'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create template: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:email_templates,slug,' . $emailTemplate->id,
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $emailTemplate->update($validatedData);

            $placeholderNames = $this->extractPlaceholders($emailTemplate->subject . ' ' . $emailTemplate->body_html);
            $placeholderIds = PlaceholderDefinition::whereIn('name', $placeholderNames)->pluck('id');

            $emailTemplate->placeholders()->sync($placeholderIds);

            DB::commit();

            return response()->json($emailTemplate->load('placeholders'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update template: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmailTemplate $emailTemplate)
    {
        $emailTemplate->delete();
        return response()->json(null, 204);
    }

    /**
     * Helper function to extract placeholder names from a string.
     * This will be used to automatically link templates to definitions.
     *
     * @param string $content
     * @return array
     */
    private function extractPlaceholders(string $content): array
    {
        $placeholders = [];
        // The corrected regex now includes the space character (\s) inside the capture group
        // to correctly match placeholder names like "Client Name".
        preg_match_all('/\{\{(\s*[\w\s]+\s*)\}\}/', $content, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $match) {
                $placeholders[] = trim($match);
            }
        }
        return array_unique($placeholders);
    }
}
