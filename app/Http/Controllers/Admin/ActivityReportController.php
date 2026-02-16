<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityReportController extends Controller
{
    /**
     * Display the activity report dashboard.
     */
    public function index()
    {
        return Inertia::render('Admin/Activity/Index');
    }
}
