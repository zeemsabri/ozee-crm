<?php

// This script tests the TransactionObserver functionality
// It creates a test transaction and verifies that the project's profit_margin_percentage is updated

use App\Models\Project;
use App\Models\Transaction;

// Get a project to test with
$project = Project::first();

if (! $project) {
    echo "No projects found. Please create a project first.\n";
    exit(1);
}

echo "Testing with Project ID: {$project->id}, Name: {$project->name}\n";
echo 'Initial profit_margin_percentage: '.($project->profit_margin_percentage ?? 'null')."\n";

// Create a test income transaction
$incomeTransaction = new Transaction([
    'project_id' => $project->id,
    'description' => 'Test Income Transaction',
    'amount' => 1000.00,
    'currency' => 'USD',
    'type' => 'income',
]);

$incomeTransaction->save();
echo "Created income transaction with ID: {$incomeTransaction->id}\n";

// Refresh the project from the database
$project->refresh();
echo 'After income transaction, profit_margin_percentage: '.($project->profit_margin_percentage ?? 'null')."\n";

// Create a test expense transaction
$expenseTransaction = new Transaction([
    'project_id' => $project->id,
    'description' => 'Test Expense Transaction',
    'amount' => 400.00,
    'currency' => 'USD',
    'type' => 'expense',
]);

$expenseTransaction->save();
echo "Created expense transaction with ID: {$expenseTransaction->id}\n";

// Refresh the project from the database
$project->refresh();
echo 'After expense transaction, profit_margin_percentage: '.($project->profit_margin_percentage ?? 'null')."\n";

// Expected profit margin: (1000 - 400) / 1000 * 100 = 60%
echo "Expected profit margin: 60.00%\n";

// Clean up test transactions
echo "Cleaning up test transactions...\n";
$incomeTransaction->delete();
$expenseTransaction->delete();

// Refresh the project from the database
$project->refresh();
echo 'After cleanup, profit_margin_percentage: '.($project->profit_margin_percentage ?? 'null')."\n";

echo "Test completed.\n";
