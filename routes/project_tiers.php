<?php

use App\Http\Controllers\Admin\ProjectTierController;
use Illuminate\Support\Facades\Route;

// Admin routes for Project Tier management
Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('admin')->name('admin.')->group(function () {
        // Project Tier management routes - requires view_project_tiers permission
        Route::get('/project-tiers', [ProjectTierController::class, 'index'])
            ->middleware(['permission:view_project_tiers'])
            ->name('project-tiers.index');

        // The following routes require create/edit/delete permissions
        Route::post('/project-tiers', [ProjectTierController::class, 'store'])
            ->middleware(['permission:create_project_tiers'])
            ->name('project-tiers.store');

        Route::put('/project-tiers/{projectTier}', [ProjectTierController::class, 'update'])
            ->middleware(['permission:edit_project_tiers'])
            ->name('project-tiers.update');

        Route::delete('/project-tiers/{projectTier}', [ProjectTierController::class, 'destroy'])
            ->middleware(['permission:delete_project_tiers'])
            ->name('project-tiers.destroy');
    });
});

// API routes for Project Tier AJAX operations
Route::middleware(['auth:sanctum'])->prefix('api')->group(function () {
    Route::apiResource('project-tiers', \App\Http\Controllers\Api\ProjectTierController::class);
});
