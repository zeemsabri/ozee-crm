<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Icon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ComponentController extends Controller
{
    /**
     * Display a listing of components, formatted for the frontend palette.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Eager load the icon relationship to get the name and SVG content efficiently.
        $components = Component::with('icon:id,name,svg_content')->get();

        // Transform the collection into a simple array of objects.
        // This ensures all components are returned without being overwritten.
        $formattedComponents = $components->map(function ($component) {
            return [
                'name' => $component->name,
                'type' => $component->type, // The unique component type (e.g., "Heading", "Avatar")
                'category'  =>  $component->category,
                // Return the raw SVG string for the frontend to render.
                'icon' => $component->icon ? $component->icon->svg_content : null,
                // The 'default' key holds the component's definition.
                'default' => $component->definition,
            ];
        });

        // Return a simple JSON array.
        return response()->json($formattedComponents);
    }

    /**
     * Store a newly created component from the frontend modal.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:255|unique:components,type',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'definition' => 'required|json',
            'icon' => 'required|array',
            'icon.name' => 'required|string|max:255',
            'icon.svg_content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        // The frontend sends the definition as a JSON string, so we decode it here.
        $definitionData = json_decode($validated['definition'], true);

        // Use a database transaction to ensure both icon and component are created successfully.
        DB::beginTransaction();
        try {
            // Find an existing icon by name or create a new one.
            $icon = Icon::updateOrCreate(
                ['name' => $validated['icon']['name']],
                ['svg_content' => $validated['icon']['svg_content']]
            );

            // Create the new component record.
            $component = Component::create([
                'type' => $validated['type'],
                'name' => $validated['name'],
                'category' => $validated['category'],
                'definition' => $definitionData,
                'icon_id' => $icon->id,
            ]);

            // If everything is successful, commit the changes to the database.
            DB::commit();

            // Return the newly created component, including its icon data.
            return response()->json($component->load('icon'), 201);

        } catch (\Exception $e) {
            // If any error occurs, roll back all database changes.
            DB::rollBack();
            return response()->json(['message' => 'Failed to create component.', 'error' => $e->getMessage()], 500);
        }
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
     * @param  \Illuminate\Http\Request  $request
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
            if (!Component::validateDefinition($definitionData)) {
                return response()->json([
                    'errors' => ['definition' => ['The component definition is invalid. It must include default size properties.']]
                ], 422);
            }
            $component->definition = $definitionData;
        }

        // Handle icon if provided
        if ($request->has('icon_svg') && $request->has('icon_name')) {
            // Validate and sanitize SVG content
            if (!Icon::validateSvgContent($request->icon_svg)) {
                return response()->json([
                    'errors' => ['icon_svg' => ['The SVG content is invalid or contains potentially malicious code.']]
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
