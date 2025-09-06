<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contexts', function (Blueprint $table) {
            $table->id();
            $table->text('summary');

            // The SOURCE of the context (Email, ProjectNote, etc.)
            $table->morphs('referencable');

            // The SUBJECT of the context (Lead, Client, Project, etc.)
            $table->morphs('linkable');

            // The user who performed the action, if applicable
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->json('meta_data')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contexts');
    }
};
