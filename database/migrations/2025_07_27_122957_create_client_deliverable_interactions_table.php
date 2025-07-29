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
        Schema::create('client_deliverable_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('revisions_requested_at')->nullable();
            $table->text('feedback_text')->nullable();
            $table->timestamps();

            // Add unique constraint on deliverable_id and client_id
            $table->unique(['deliverable_id', 'client_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_deliverable_interactions');
    }
};
