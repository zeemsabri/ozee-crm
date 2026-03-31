<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MagicLink;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ExternalTokenController extends Controller
{
    /**
     * Display a listing of external tokens.
     */
    public function index()
    {
        $tokens = MagicLink::where('type', 'external')
            ->with('project')
            ->latest()
            ->get();

        $projects = Project::select('id', 'name')->get();

        return Inertia::render('Admin/ExternalTokens/Index', [
            'tokens' => $tokens,
            'projects' => $projects,
        ]);
    }

    /**
     * Store a newly created external token in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:255',
            'email' => 'nullable|email',
            'project_id' => 'nullable|exists:projects,id',
            'expires_at' => 'nullable|date|after:now',
            'max_uses' => 'nullable|integer|min:1',
            'whitelist_domains' => 'nullable|array',
            'whitelist_ips' => 'nullable|array',
        ]);

        MagicLink::create([
            'label' => $request->label,
            'email' => $request->email,
            'project_id' => $request->project_id ?: null,
            'token' => Str::random(64),
            'type' => 'external',
            'expires_at' => $request->expires_at ?: null,
            'max_uses' => $request->max_uses,
            'whitelist' => [
                'domains' => $request->whitelist_domains ?? [],
                'ips' => $request->whitelist_ips ?? [],
            ],
            'used' => false,
        ]);

        return back()->with('success', 'External token created successfully.');
    }

    /**
     * Update the specified external token in storage.
     */
    public function update(Request $request, $id)
    {
        $magicLink = MagicLink::findOrFail($id);

        if ($magicLink->type !== 'external') {
            return back()->with('error', 'Only external tokens can be edited from this interface.');
        }

        $request->validate([
            'label' => 'required|string|max:255',
            'email' => 'nullable|email',
            'project_id' => 'nullable|exists:projects,id',
            'expires_at' => 'nullable|date',
            'max_uses' => 'nullable|integer|min:1',
            'whitelist_domains' => 'nullable|array',
            'whitelist_ips' => 'nullable|array',
        ]);

        $magicLink->update([
            'label' => $request->label,
            'email' => $request->email,
            'project_id' => $request->project_id ?: null,
            'expires_at' => $request->expires_at ?: null,
            'max_uses' => $request->max_uses,
            'whitelist' => [
                'domains' => $request->whitelist_domains ?? [],
                'ips' => $request->whitelist_ips ?? [],
            ],
        ]);

        return back()->with('success', 'External token updated successfully.');
    }

    /**
     * Remove the specified external token from storage.
     */
    public function destroy(MagicLink $magicLink)
    {
        if ($magicLink->type !== 'external') {
            return back()->with('error', 'Only external tokens can be deleted from this interface.');
        }

        $magicLink->delete();

        return back()->with('success', 'Token deleted successfully.');
    }
}
