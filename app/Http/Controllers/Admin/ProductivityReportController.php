<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class ProductivityReportController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Productivity/Index');
    }

    // Removed generateReport method as it's moved to API

}
