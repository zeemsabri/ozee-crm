<?php

use App\Http\Controllers\Admin\MonthlyBudgetController;
use App\Http\Controllers\Admin\ProjectTierController;
use Illuminate\Support\Facades\Route;

// Admin routes for Project Tier and Monthly Budget management
Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('admin')->group(function () {
        // Project Tier management routes - requires view_project_tiers permission
        Route::get('/project-tiers', [ProjectTierController::class, 'index'])
            ->middleware(['permission:view_project_tiers']);

        // The following routes require create/edit/delete permissions
        Route::post('/project-tiers', [ProjectTierController::class, 'store'])
            ->middleware(['permission:create_project_tiers']);

        Route::put('/project-tiers/{projectTier}', [ProjectTierController::class, 'update'])
            ->middleware(['permission:edit_project_tiers']);

        Route::delete('/project-tiers/{projectTier}', [ProjectTierController::class, 'destroy'])
            ->middleware(['permission:delete_project_tiers']);

        // Monthly Budget management routes - requires view_monthly_budgets permission
        Route::get('/monthly-budgets', [MonthlyBudgetController::class, 'index'])
            ->middleware(['permission:view_monthly_budgets'])
            ->name('admin.monthly-budgets.index');

        // API routes for Monthly Budget management
        Route::get('/monthly-budgets/all', [MonthlyBudgetController::class, 'getAllBudgets'])
            ->middleware(['permission:view_monthly_budgets'])
            ->name('admin.monthly-budgets.all');

        Route::get('/monthly-budgets/current', [MonthlyBudgetController::class, 'getCurrentBudget'])
            ->middleware(['permission:view_monthly_budgets'])
            ->name('admin.monthly-budgets.current');

        Route::get('/monthly-budgets/{monthlyBudget}', [MonthlyBudgetController::class, 'show'])
            ->middleware(['permission:view_monthly_budgets'])
            ->name('admin.monthly-budgets.show');

        // The following routes require manage_monthly_budgets permission
        Route::post('/monthly-budgets', [MonthlyBudgetController::class, 'store'])
            ->middleware(['permission:manage_monthly_budgets'])
            ->name('admin.monthly-budgets.store');

        Route::put('/monthly-budgets/{monthlyBudget}', [MonthlyBudgetController::class, 'update'])
            ->middleware(['permission:manage_monthly_budgets'])
            ->name('admin.monthly-budgets.update');

        Route::delete('/monthly-budgets/{monthlyBudget}', [MonthlyBudgetController::class, 'destroy'])
            ->middleware(['permission:manage_monthly_budgets'])
            ->name('admin.monthly-budgets.destroy');

        // Live Status routes
        Route::get('/live-status', [\App\Http\Controllers\Admin\LiveStatusController::class, 'index'])
            ->name('admin.live-status.index');
        Route::get('/live-status/{user}/logs', [\App\Http\Controllers\Admin\LiveStatusController::class, 'logs'])
            ->name('admin.live-status.logs');

        // External Tokens management routes
        Route::get('/external-tokens', [\App\Http\Controllers\Admin\ExternalTokenController::class, 'index'])
            ->name('admin.external-tokens.index');
        Route::post('/external-tokens', [\App\Http\Controllers\Admin\ExternalTokenController::class, 'store'])
            ->name('admin.external-tokens.store');
        Route::put('/external-tokens/{magicLink}', [\App\Http\Controllers\Admin\ExternalTokenController::class, 'update'])
            ->name('admin.external-tokens.update');
        Route::delete('/external-tokens/{magicLink}', [\App\Http\Controllers\Admin\ExternalTokenController::class, 'destroy'])
            ->name('admin.external-tokens.destroy');

        // Stripe Configuration routes
        Route::get('/stripe-configurations', [\App\Http\Controllers\Admin\StripeConfigurationController::class, 'index'])
            ->name('admin.stripe-configurations.index');
        Route::post('/stripe-configurations', [\App\Http\Controllers\Admin\StripeConfigurationController::class, 'store'])
            ->name('admin.stripe-configurations.store');
        Route::put('/stripe-configurations/{stripeConfiguration}', [\App\Http\Controllers\Admin\StripeConfigurationController::class, 'update'])
            ->name('admin.stripe-configurations.update');
        Route::delete('/stripe-configurations/{stripeConfiguration}', [\App\Http\Controllers\Admin\StripeConfigurationController::class, 'destroy'])
            ->name('admin.stripe-configurations.destroy');
    });
});
