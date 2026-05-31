<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectExpendableController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/ProjectExpendables/Index', [
            'transaction_types' => TransactionType::orderBy('name')->get(),
            'initial_project_id' => $request->input('project_id'),
        ]);
    }
}
