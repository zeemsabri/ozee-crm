<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Presentation;
use App\Models\Slide;
use App\Models\ContentBlock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PresentationController extends Controller
{
    private const PRESENTABLE_WHITELIST = [
        'App\\Models\\Client',
        'App\\Models\\Lead',
    ];

    public function index(Request $request)
    {
        $presentations = Presentation::query()
            ->orderByDesc('id')
            ->paginate(15);
        return response()->json($presentations);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'presentable_id' => ['required','integer'],
            'presentable_type' => ['required','string', Rule::in(self::PRESENTABLE_WHITELIST)],
            'title' => ['required','string','max:255'],
            'type' => ['required','string','max:50'],
        ]);

        $presentation = Presentation::create($data);
        return response()->json($presentation, 201);
    }

    public function show($id)
    {
        $presentation = Presentation::with([
            'presentable',
            'metadata',
            'slides' => function ($q) {
                $q->orderBy('display_order')
                  ->with(['contentBlocks' => function ($qq) { $qq->orderBy('display_order'); }]);
            },
        ])->findOrFail($id);

        return response()->json($presentation);
    }

    public function update(Request $request, $id)
    {
        $presentation = Presentation::findOrFail($id);
        $data = $request->validate([
            'title' => ['sometimes','string','max:255'],
            'type' => ['sometimes','string','max:50'],
        ]);
        $presentation->update($data);
        return response()->json($presentation);
    }

    public function destroy($id)
    {
        $presentation = Presentation::findOrFail($id);
        $presentation->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // Slides
    public function storeSlide(Request $request, $presentationId)
    {
        $presentation = Presentation::findOrFail($presentationId);
        $data = $request->validate([
            'template_name' => ['required','string','max:100'],
            'title' => ['nullable','string','max:255'],
            'display_order' => ['nullable','integer'],
        ]);
        if (!isset($data['display_order'])) {
            $data['display_order'] = $presentation->slides()->max('display_order') + 1;
        }
        $slide = $presentation->slides()->create($data);
        return response()->json($slide, 201);
    }

    public function updateSlide(Request $request, $id)
    {
        $slide = Slide::findOrFail($id);
        $data = $request->validate([
            'template_name' => ['sometimes','string','max:100'],
            'title' => ['sometimes','nullable','string','max:255'],
        ]);
        $slide->update($data);
        return response()->json($slide);
    }

    public function reorderSlides(Request $request)
    {
        $validated = $request->validate([
            'orders' => ['required','array'],
            'orders.*.id' => ['required','integer','exists:slides,id'],
            'orders.*.display_order' => ['required','integer'],
        ]);

        foreach ($validated['orders'] as $item) {
            Slide::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
        }
        return response()->json(['message' => 'Slides reordered']);
    }

    public function destroySlide($id)
    {
        $slide = Slide::findOrFail($id);
        $slide->delete();
        return response()->json(['message' => 'Slide deleted']);
    }

    // Content Blocks
    public function storeContentBlock(Request $request, $slideId)
    {
        $slide = Slide::findOrFail($slideId);
        $data = $request->validate([
            'block_type' => ['required','string','max:100'],
            'content_data' => ['required','array'],
            'display_order' => ['nullable','integer'],
        ]);
        if (!isset($data['display_order'])) {
            $data['display_order'] = $slide->contentBlocks()->max('display_order') + 1;
        }
        $contentBlock = $slide->contentBlocks()->create($data);
        return response()->json($contentBlock, 201);
    }

    public function updateContentBlock(Request $request, $id)
    {
        $contentBlock = ContentBlock::findOrFail($id);
        $data = $request->validate([
            'content_data' => ['required','array'],
        ]);
        $contentBlock->update($data);
        return response()->json($contentBlock);
    }

    public function reorderContentBlocks(Request $request)
    {
        $validated = $request->validate([
            'orders' => ['required','array'],
            'orders.*.id' => ['required','integer','exists:content_blocks,id'],
            'orders.*.display_order' => ['required','integer'],
        ]);
        foreach ($validated['orders'] as $item) {
            ContentBlock::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
        }
        return response()->json(['message' => 'Content blocks reordered']);
    }

    public function destroyContentBlock($id)
    {
        $contentBlock = ContentBlock::findOrFail($id);
        $contentBlock->delete();
        return response()->json(['message' => 'Content block deleted']);
    }
}
