<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Icon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ComponentController extends Controller
{
    /**
     * Display a listing of components with their icons.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $components = Component::with('icon')->get();

        return response()->json($components);
    }

    /**
     * Store a newly created component with optional icon.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:components',
            'type' => 'required|string|max:255',
            'definition' => 'required|json',
            'icon_svg' => 'nullable|string',
            'icon_name' => 'required_with:icon_svg|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate component definition
        $definitionData = json_decode($request->definition, true);
        if (! Component::validateDefinition($definitionData)) {
            return response()->json([
                'errors' => ['definition' => ['The component definition is invalid. It must include default size properties.']],
            ], 422);
        }

        // Handle icon if provided
        $iconId = null;
        if ($request->has('icon_svg') && $request->has('icon_name')) {
            // Validate and sanitize SVG content
            if (! Icon::validateSvgContent($request->icon_svg)) {
                return response()->json([
                    'errors' => ['icon_svg' => ['The SVG content is invalid or contains potentially malicious code.']],
                ], 422);
            }

            $sanitizedSvg = Icon::sanitizeSvgContent($request->icon_svg);

            // Create or update icon
            $icon = Icon::updateOrCreate(
                ['name' => $request->icon_name],
                ['svg_content' => $sanitizedSvg]
            );

            $iconId = $icon->id;

            activity()
                ->performedOn($icon)
                ->log("Icon {$icon->name} created or updated");
        }

        // Create component
        $component = Component::create([
            'name' => $request->name,
            'type' => $request->type,
            'definition' => $definitionData,
            'icon_id' => $iconId,
        ]);

        activity()
            ->performedOn($component)
            ->log("Component {$component->name} created");

        return response()->json([
            'component' => $component->load('icon'),
        ], 201);
    }

    /**
     * Display the specified component.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $component = Component::with('icon')->findOrFail($id);

        return response()->json($component);
    }

    /**
     * Update the specified component.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $component = Component::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('components')->ignore($component->id),
            ],
            'type' => 'sometimes|string|max:255',
            'definition' => 'sometimes|json',
            'icon_svg' => 'nullable|string',
            'icon_name' => 'required_with:icon_svg|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update component fields if provided
        if ($request->has('name')) {
            $component->name = $request->name;
        }

        if ($request->has('type')) {
            $component->type = $request->type;
        }

        if ($request->has('definition')) {
            $definitionData = json_decode($request->definition, true);
            if (! Component::validateDefinition($definitionData)) {
                return response()->json([
                    'errors' => ['definition' => ['The component definition is invalid. It must include default size properties.']],
                ], 422);
            }
            $component->definition = $definitionData;
        }

        // Handle icon if provided
        if ($request->has('icon_svg') && $request->has('icon_name')) {
            // Validate and sanitize SVG content
            if (! Icon::validateSvgContent($request->icon_svg)) {
                return response()->json([
                    'errors' => ['icon_svg' => ['The SVG content is invalid or contains potentially malicious code.']],
                ], 422);
            }

            $sanitizedSvg = Icon::sanitizeSvgContent($request->icon_svg);

            // Create or update icon
            $icon = Icon::updateOrCreate(
                ['name' => $request->icon_name],
                ['svg_content' => $sanitizedSvg]
            );

            $component->icon_id = $icon->id;

            activity()
                ->performedOn($icon)
                ->log("Icon {$icon->name} created or updated");
        }

        $component->save();

        activity()
            ->performedOn($component)
            ->log("Component {$component->name} updated");

        return response()->json([
            'component' => $component->load('icon'),
        ]);
    }

    /**
     * Remove the specified component.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $component = Component::findOrFail($id);
        $componentName = $component->name;

        $component->delete();

        activity()
            ->withProperties(['component_name' => $componentName])
            ->log("Component {$componentName} deleted");

        return response()->json(null, 204);
    }
}
