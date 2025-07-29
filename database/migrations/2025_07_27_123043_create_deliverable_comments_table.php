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
        Schema::create('deliverable_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->text('comment_text');
            $table->string('context')->nullable()->comment('e.g., paragraph 2, image 1');
            $table->timestamp('resolved_at')->nullable()->comment('for internal use');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliverable_comments');
    }
};
