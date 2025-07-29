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
        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_member_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->comment('e.g., blog_post, design_mockup');
            $table->string('status')->default('pending_review')->comment('e.g., pending_review, approved, revisions_requested, completed');
            $table->string('content_url')->nullable();
            $table->longText('content_text')->nullable();
            $table->string('attachment_path')->nullable();
            $table->integer('version')->default(1);
            $table->foreignId('parent_deliverable_id')->nullable()->constrained('deliverables')->onDelete('set null');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('overall_approved_at')->nullable();
            $table->foreignId('overall_approved_by_client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->timestamp('due_for_review_by')->nullable();
            $table->boolean('is_visible_to_client')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliverables');
    }
};
