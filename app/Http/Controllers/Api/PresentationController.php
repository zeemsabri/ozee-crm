<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Presentation;
use App\Models\Project;
use App\Models\Slide;
use App\Models\ContentBlock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PresentationController extends Controller
{
    private const PRESENTABLE_WHITELIST = [
        'App\\Models\\Client',
        'App\\Models\\Lead',
    ];

    private function deepClonePresentation(Presentation $source, array $overrides = []): Presentation
    {
        // Duplicate presentation row
        $new = $source->replicate(['share_token']); // regenerate share token via booted
        foreach ($overrides as $k => $v) {
            $new->{$k} = $v;
        }
        $new->save();

        // Duplicate slides and their content blocks
        $source->load(['slides.contentBlocks']);
        foreach ($source->slides as $slide) {
            $newSlide = $slide->replicate(['presentation_id']);
            $newSlide->presentation_id = $new->id;
            $newSlide->save();

            foreach ($slide->contentBlocks as $block) {
                $newBlock = $block->replicate(['slide_id']);
                $newBlock->slide_id = $newSlide->id;
                $newBlock->save();
            }
        }

        return $new->fresh(['slides.contentBlocks']);
    }

    public function index(Request $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        $query = Presentation::query()
            ->where('is_template', false);

        // If user has broad permission, return all
        if ($user && $user->hasPermission('create_presentation')) {
            $presentations = $query->orderByDesc('id')->paginate(15);
            return response()->json($presentations);
        }

        // Otherwise, restrict to invited or owned (via Lead.created_by_id)
        $query->where(function ($q) use ($user) {
            // invited via pivot
            $q->whereHas('users', function ($qq) use ($user) {
                $qq->where('user_id', optional($user)->id);
            });
        })->orWhere(function ($q) use ($user) {
            // owned via Lead.created_by_id
            $q->where('presentable_type', \App\Models\Lead::class)
              ->whereHasMorph('presentable', [\App\Models\Lead::class], function ($qqq) use ($user) {
                  $qqq->where('created_by_id', optional($user)->id);
              });
        });

        $presentations = $query->orderByDesc('id')->paginate(15);
        return response()->json($presentations);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'presentable_id' => ['required','integer'],
            'presentable_type' => ['required','string', Rule::in(self::PRESENTABLE_WHITELIST)],
            'title' => ['required','string','max:255'],
            'type' => ['required','string','max:50'],
            // New optional inputs to support creation from template or slides
            'template_id' => ['nullable','integer','exists:presentations,id'],
            'source_slide_ids' => ['nullable','array'],
            'source_slide_ids.*' => ['integer','exists:slides,id'],
        ]);

        // Enforce mutual exclusivity between template_id and source_slide_ids
        if (!empty($data['template_id']) && !empty($data['source_slide_ids'])) {
            return response()->json(['message' => 'Provide either template_id or source_slide_ids, not both.'], 422);
        }

        // Base presentation record (polymorphic-only association)
        $presentation = Presentation::create([
            'presentable_id' => $data['presentable_id'],
            'presentable_type' => $data['presentable_type'],
            'title' => $data['title'],
            'type' => $data['type'],
            'is_template' => false,
        ]);

        // If template_id provided, clone template into the new presentation (slides + blocks)
        if (!empty($data['template_id'])) {
            $template = Presentation::with('slides.contentBlocks')->findOrFail($data['template_id']);
            // Safety: ensure selected source is a template
            if (!$template->is_template) {
                return response()->json(['message' => 'Selected presentation is not a template'], 422);
            }
            // Copy slides into the newly created presentation
            $maxOrder = (int) $presentation->slides()->max('display_order');
            foreach ($template->slides as $slide) {
                $newSlide = $slide->replicate(['presentation_id']);
                $newSlide->presentation_id = $presentation->id;
                $newSlide->display_order = ++$maxOrder;
                $newSlide->save();
                foreach ($slide->contentBlocks as $block) {
                    $newBlock = $block->replicate(['slide_id']);
                    $newBlock->slide_id = $newSlide->id;
                    $newBlock->save();
                }
            }
        }

        // If source_slide_ids provided, copy those slides into the new presentation
        if (!empty($data['source_slide_ids'])) {
            $slides = Slide::with('contentBlocks')
                ->whereIn('id', $data['source_slide_ids'])
                ->orderBy('display_order')
                ->get();
            $maxOrder = (int) $presentation->slides()->max('display_order');
            foreach ($slides as $slide) {
                $newSlide = $slide->replicate(['presentation_id']);
                $newSlide->presentation_id = $presentation->id;
                $newSlide->display_order = ++$maxOrder;
                $newSlide->save();
                foreach ($slide->contentBlocks as $block) {
                    $newBlock = $block->replicate(['slide_id']);
                    $newBlock->slide_id = $newSlide->id;
                    $newBlock->save();
                }
            }
        }

        return response()->json($presentation->fresh(['slides.contentBlocks']), 201);
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

    // Templates & Duplication
    public function templates()
    {
        $items = Presentation::query()
            ->where('is_template', true)
            ->withCount('slides')
            ->orderBy('title')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'is_template' => (bool)$p->is_template,
                    'slide_count' => $p->slides_count,
                ];
            });
        return response()->json(['data' => $items]);
    }

    public function duplicate($id)
    {
        $source = Presentation::with('slides.contentBlocks')->findOrFail($id);

        $new = $this->deepClonePresentation($source, [
            'is_template' => false,
            // keep presentable as is
            'title' => rtrim($source->title) . ' (Copy)',
        ]);

        return response()->json($new, 201);
    }

    public function saveAsTemplate($id)
    {
        $source = Presentation::with('slides.contentBlocks')->findOrFail($id);

        $new = $this->deepClonePresentation($source, [
            'is_template' => true,
            'presentable_id' => 1,
            'presentable_type' => Project::class,
            'title' => rtrim($source->title) . ' (Template)',
        ]);

        return response()->json($new, 201);
    }

    public function copySlides(Request $request, $targetId)
    {
        $target = Presentation::findOrFail($targetId);
        $validated = $request->validate([
            'source_slide_ids' => ['required','array'],
            'source_slide_ids.*' => ['integer','exists:slides,id'],
        ]);

        $slides = Slide::with('contentBlocks')
            ->whereIn('id', $validated['source_slide_ids'])
            ->orderBy('display_order')
            ->get();

        // Determine next display_order starting point in target
        $maxOrder = (int) $target->slides()->max('display_order');

        foreach ($slides as $slide) {
            $newSlide = $slide->replicate(['presentation_id']);
            $newSlide->presentation_id = $target->id;
            $newSlide->display_order = ++$maxOrder;
            $newSlide->save();

            foreach ($slide->contentBlocks as $block) {
                $newBlock = $block->replicate(['slide_id']);
                $newBlock->slide_id = $newSlide->id;
                $newBlock->save();
            }
        }

        return response()->json(['message' => 'Slides copied']);
    }

    /**
     * Invite a user to collaborate on a presentation.
     * POST /api/presentations/{id}/invite
     */
    public function invite(Request $request, $id)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', Rule::in(['editor','viewer'])],
        ]);

        $presentation = Presentation::findOrFail($id);

        // Allow if current user is invited editor/viewer or has general permission
        if (!$user->hasPermission('create_presentation')) {
            $isCollaborator = $presentation->users()->where('users.id', $user->id)->exists();
            if (!$isCollaborator) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $role = $data['role'] ?? 'editor';
        $presentation->users()->syncWithoutDetaching([
            $data['user_id'] => ['role' => $role],
        ]);

        // Return minimal list of collaborators
        $collaborators = $presentation->users()->select('users.id','users.name','users.email')->get();
        return response()->json(['message' => 'Invitation sent', 'collaborators' => $collaborators]);
    }

}
