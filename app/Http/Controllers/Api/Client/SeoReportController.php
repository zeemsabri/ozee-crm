<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SeoReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SeoReportController extends Controller
{
    /**
     * Store a newly created or update an existing SEO report.
     */
    public function store(Request $request, Project $project)
    {
        $validator = Validator::make($request->all(), [
            'report_date' => 'required|string|date_format:Y-m',
            'data' => 'required|string|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Parse the report date to a Carbon date object and set day to 01
        $reportDate = Carbon::parse($request->report_date . '-01');

        // Decode JSON data
        $data = json_decode($request->data, true);

        // Check if a report already exists for this project and date
        $report = SeoReport::where('project_id', $project->id)
            ->where('report_date', $reportDate)
            ->first();

        if ($report) {
            // Update existing report
            $report->data = $data;
            $report->save();

            return response()->json($report, 200);
        } else {
            // Create new report
            $report = new SeoReport([
                'project_id' => $project->id,
                'report_date' => $reportDate,
                'data' => $data,
            ]);

            $report->save();

            return response()->json($report, 201);
        }
    }

    /**
     * Display the specified SEO report.
     */
    public function show(Request $request, Project $project, string $yearMonth)
    {
        // Validate year-month format
        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            return response()->json(['error' => 'Invalid date format. Use YYYY-MM format.'], 400);
        }

        // Construct the report date
        $reportDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();

        // Find the report
        $report = SeoReport::where('project_id', $project->id)
            ->where('report_date', $reportDate)
            ->first();

        if (!$report) {
            return response()->json(['error' => 'Report not found'], 404);
        }

        // Return just the data field which is already cast to an array
        return response()->json($report->data, 200);
    }

    /**
     * Get available months for SEO reports.
     */
    public function getAvailableMonths(Request $request, Project $project)
    {
        // Get all unique report dates for this project
        $dates = SeoReport::where('project_id', $project->id)
            ->orderBy('report_date', 'desc')
            ->pluck('report_date');

        // Format dates to YYYY-MM strings
        $formattedDates = $dates->map(function ($date) {
            return Carbon::parse($date)->format('Y-m');
        });

        return response()->json($formattedDates, 200);
    }

    /**
     * Get the total count of SEO reports for a project.
     */
    public function getCount(Request $request, Project $project)
    {
        // Count the number of SEO reports for this project
        $count = SeoReport::where('project_id', $project->id)->count();

        return response()->json(['count' => $count], 200);
    }
}
