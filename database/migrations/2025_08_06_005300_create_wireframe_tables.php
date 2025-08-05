<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wireframes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });

        Schema::create('wireframe_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wireframe_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('version_number');
            $table->json('data');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
            $table->unique(['wireframe_id', 'version_number']);
        });

        Schema::create('icons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('svg_content');
            $table->timestamps();
        });

        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type');
            $table->json('definition');
            $table->foreignId('icon_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wireframe_versions');
        Schema::dropIfExists('wireframes');
        Schema::dropIfExists('components');
        Schema::dropIfExists('icons');
    }
};
