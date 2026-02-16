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
        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Extracted from payload.data
            $table->string('domain')->index(); 
            $table->text('url'); 
            $table->text('title')->nullable();
            $table->boolean('is_incognito')->default(false);
            $table->boolean('is_audible')->default(false);
            $table->integer('tab_count')->default(0);

            // Extracted from payload (Context Metadata)
            $table->string('hostname')->nullable(); // e.g., Zeeshans-Computer.local
            $table->string('browser')->nullable();  // e.g., chrome

            // Client-side exact timestamp
            $table->timestamp('recorded_at')->index(); 
            $table->timestamps(); // Laravel created_at / updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activities');
    }
};
