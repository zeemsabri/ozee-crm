<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductivityReportController extends Controller
{
    public function index(Request $request)
    {
        $users = User::orderBy('name')->get()->map(fn($u) => [
            'value' => $u->id,
            'label' => $u->name,
            'avatar' => $u->avatar_url ?? "https://ui-avatars.com/api/?name=" . urlencode($u->name)
        ]);

        return Inertia::render('Admin/Productivity/Index', [
            'users' => $users
        ]);
    }
}
