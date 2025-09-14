<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromptController extends Controller
{
    public function index()
    {
        return response()->json(Prompt::orderByDesc('id')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'system_prompt_text' => ['required', 'string'],
            'model_name' => ['sometimes', 'string', 'max:255'],
            'generation_config' => ['nullable', 'array'],
            'template_variables' => ['nullable', 'array'],
            'response_variables' => ['nullable', 'array'],
            'response_json_template' => ['nullable', 'array'],
            'status' => ['sometimes', 'string', 'max:50'],
        ]);

        // Enforce unique (name, version)
        $version = $data['version'] ?? 1;
        $exists = Prompt::where('name', $data['name'])->where('version', $version)->exists();
        if ($exists) {
            return response()->json(['message' => 'The combination of name and version has already been taken.'], 422);
        }

        $data['version'] = $version;
        $prompt = Prompt::create($data);
        return response()->json($prompt, 201);
    }

    public function show(Prompt $prompt)
    {
        return response()->json($prompt);
    }

    public function update(Request $request, Prompt $prompt)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'system_prompt_text' => ['sometimes', 'required', 'string'],
            'model_name' => ['sometimes', 'string', 'max:255'],
            'generation_config' => ['nullable', 'array'],
            'template_variables' => ['nullable', 'array'],
            'response_variables' => ['nullable', 'array'],
            'response_json_template' => ['nullable', 'array'],
            'status' => ['sometimes', 'string', 'max:50'],
        ]);

        $newName = $data['name'] ?? $prompt->name;
        $newVersion = $data['version'] ?? $prompt->version;
        $exists = Prompt::where('name', $newName)
            ->where('version', $newVersion)
            ->where('id', '!=', $prompt->id)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'The combination of name and version has already been taken.'], 422);
        }

        $prompt->update($data);
        return response()->json($prompt);
    }

    public function destroy(Prompt $prompt)
    {
        $prompt->delete();
        return response()->noContent();
    }
}
