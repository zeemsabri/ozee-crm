<?php

require __DIR__ . '/vendor/autoload.php';

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\SeoReport;

// Mock test for SeoReportController functionality

echo "Testing SeoReport functionality...\n\n";

// 1. Test creating a new SeoReport
echo "1. Testing SeoReport creation:\n";

// Find a project to use for testing
$project = Project::first();

if (!$project) {
    echo "Error: No projects found in the database. Please create a project first.\n";
    exit(1);
}

echo "Using project ID: {$project->id}\n";

// Create a test report date (first day of current month)
$reportDate = Carbon::now()->startOfMonth()->format('Y-m');
echo "Using report date: {$reportDate}\n";

// Create test data
$testData = [
    'keyMetrics' => [
        'organicTrafficValue' => 12500,
        'averagePositionValue' => 25.4,
        'totalBacklinksValue' => '2,000',
    ],
    'clicksImpressions' => [
        'labels' => ['2025-07-01', '2025-07-02', '2025-07-03'],
        'clicks' => [10, 15, 12],
        'impressions' => [500, 600, 550],
    ],
];

// Convert to JSON
$jsonData = json_encode($testData);

echo "Test data created.\n";

// Create or update the report
$existingReport = SeoReport::where('project_id', $project->id)
    ->where('report_date', Carbon::createFromFormat('Y-m', $reportDate)->startOfMonth())
    ->first();

if ($existingReport) {
    echo "Existing report found. Updating...\n";
    $existingReport->data = $testData;
    $existingReport->save();
    echo "Report updated successfully.\n";
} else {
    echo "No existing report found. Creating new report...\n";
    $report = new SeoReport([
        'project_id' => $project->id,
        'report_date' => Carbon::createFromFormat('Y-m', $reportDate)->startOfMonth(),
        'data' => $testData,
    ]);

    $report->save();
    echo "New report created successfully with ID: {$report->id}\n";
}

// 2. Test retrieving a report
echo "\n2. Testing SeoReport retrieval:\n";

$retrievedReport = SeoReport::where('project_id', $project->id)
    ->where('report_date', Carbon::createFromFormat('Y-m', $reportDate)->startOfMonth())
    ->first();

if ($retrievedReport) {
    echo "Report retrieved successfully.\n";
    echo "Report ID: {$retrievedReport->id}\n";
    echo "Project ID: {$retrievedReport->project_id}\n";
    echo "Report Date: {$retrievedReport->report_date->format('Y-m-d')}\n";
    echo "Data sample: " . json_encode(array_slice($retrievedReport->data, 0, 2)) . "\n";
} else {
    echo "Error: Failed to retrieve the report.\n";
}

// 3. Test getting available months
echo "\n3. Testing available months retrieval:\n";

$availableMonths = SeoReport::where('project_id', $project->id)
    ->orderBy('report_date', 'desc')
    ->pluck('report_date')
    ->map(function ($date) {
        return Carbon::parse($date)->format('Y-m');
    });

echo "Available months: " . implode(', ', $availableMonths->toArray()) . "\n";

echo "\nTest completed successfully!\n";
