<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            // Polymorphic relation to any model (e.g., Task)
            $table->unsignedBigInteger('fileable_id');
            $table->string('fileable_type');
            // Optional: project linkage for quick filtering
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            // Web link or storage path (we will store Google Drive share link here)
            $table->string('path')->nullable();
            $table->string('google_drive_file_id')->nullable();
            $table->string('thumbnail')->nullable();
            $table->timestamps();

            $table->index(['fileable_type', 'fileable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
