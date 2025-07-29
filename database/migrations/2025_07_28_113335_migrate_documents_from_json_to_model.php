<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Project;
use App\Models\Document;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migrates document data from JSON field in Project model to Document model.
     */
    public function up(): void
    {
        // Get all projects with non-empty documents JSON field
        $projects = Project::whereNotNull('documents')->get();

        $migratedCount = 0;
        $errorCount = 0;

        foreach ($projects as $project) {
            // Skip if documents is not an array or is empty
            if (!is_array($project->documents) || empty($project->documents)) {
                continue;
            }

            foreach ($project->documents as $documentData) {
                try {
                    // Create a new Document model instance
                    $document = new Document([
                        'project_id' => $project->id,
                        'path' => $documentData['path'] ?? null,
                        'filename' => $documentData['filename'] ?? 'Unknown',
                        'google_drive_file_id' => $documentData['google_drive_file_id'] ?? null,
                        'upload_error' => $documentData['upload_error'] ?? null,
                    ]);

                    // Save the Document to the database
                    $document->save();
                    $migratedCount++;
                } catch (\Exception $e) {
                    Log::error('Error migrating document: ' . $e->getMessage(), [
                        'project_id' => $project->id,
                        'document_data' => $documentData,
                        'error' => $e->getMessage(),
                    ]);
                    $errorCount++;
                }
            }
        }

        Log::info('Document migration completed', [
            'migrated_count' => $migratedCount,
            'error_count' => $errorCount,
        ]);
    }

    /**
     * Reverse the migrations.
     * This is a one-way migration, so down() doesn't restore the JSON data.
     * The documents table would need to be manually emptied if needed.
     */
    public function down(): void
    {
        Log::info('Document migration rollback called. Note: This does not restore JSON data.');
    }
};
